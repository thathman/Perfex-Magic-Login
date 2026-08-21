<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Expose Magic Login tags in Perfex's merge-field picker and resolve them at
 * the last safe point before an email template is parsed. This gives us the
 * actual customer recipient and relation context without modifying core mail
 * classes or generating unused tokens for every email.
 */

hooks()->add_filter('available_merge_fields', 'magic_login_available_merge_fields', 20);
hooks()->add_filter('before_parse_email_template_message', 'magic_login_prepare_email_template', 20);

function magic_login_available_merge_fields($available)
{
    $definitions = [
        [
            'name'      => 'Magic Login URL',
            'key'       => '{magic_login_url}',
            'available' => ['client', 'invoice', 'estimate', 'proposal', 'contract', 'ticket', 'project'],
        ],
        [
            'name'      => 'Magic Login Button',
            'key'       => '{magic_login_button}',
            'available' => ['client', 'invoice', 'estimate', 'proposal', 'contract', 'ticket', 'project'],
        ],
        [
            'name'      => 'Magic Login Expiry',
            'key'       => '{magic_login_expiry}',
            'available' => ['client', 'invoice', 'estimate', 'proposal', 'contract', 'ticket', 'project'],
        ],
    ];

    foreach ($available as $groupIndex => $group) {
        if (isset($group['client']) && is_array($group['client'])) {
            $available[$groupIndex]['client'] = array_merge($group['client'], $definitions);
            return $available;
        }
    }

    $available[] = ['client' => $definitions];
    return $available;
}

function magic_login_prepare_email_template($template)
{
    if (function_exists('magic_login_database_upgrade_required') && magic_login_database_upgrade_required()) {
        return $template;
    }

    if (!isset($GLOBALS['SENDING_EMAIL_TEMPLATE_CLASS'])) {
        return $template;
    }

    $mail = $GLOBALS['SENDING_EMAIL_TEMPLATE_CLASS'];
    if (!is_object($mail) || !method_exists($mail, 'is_for') || !$mail->is_for('customer')) {
        return $template;
    }

    $recipient = isset($mail->send_to) ? trim((string) $mail->send_to) : '';
    if ($recipient === '') {
        return $template;
    }

    $CI = &get_instance();
    $contact = $CI->db
        ->where('email', $recipient)
        ->where('active', 1)
        ->get(db_prefix() . 'contacts')
        ->row_array();

    if (!$contact || empty($contact['userid'])) {
        return $template;
    }

    $message = isset($template->message) ? (string) $template->message : '';
    $subject = isset($template->subject) ? (string) $template->subject : '';
    $haystack = $message . "\n" . $subject;

    $usesMagicTag = strpos($haystack, '{magic_login_url}') !== false
        || strpos($haystack, '{magic_login_button}') !== false
        || strpos($haystack, '{magic_login_expiry}') !== false;

    $autoSecure = (int) get_option('magic_login_auto_secure_email_links') === 1;
    $relType = isset($mail->rel_type) ? strtolower(trim((string) $mail->rel_type)) : '';
    $relId   = isset($mail->rel_id) && $mail->rel_id !== '' ? (int) $mail->rel_id : null;

    $linkKeys = [
        'invoice'  => '{invoice_link}',
        'estimate' => '{estimate_link}',
        'proposal' => '{proposal_link}',
        'contract' => '{contract_link}',
        'ticket'   => '{ticket_url}',
        'project'  => '{project_link}',
    ];

    $linkKey = isset($linkKeys[$relType]) ? $linkKeys[$relType] : null;
    $destination = 'clients';

    if ($linkKey && isset($mail->merge_fields[$linkKey]) && trim((string) $mail->merge_fields[$linkKey]) !== '') {
        $destination = trim((string) $mail->merge_fields[$linkKey]);
    }

    $shouldSecureEntityLink = $autoSecure && $linkKey && $destination !== 'clients';
    if (!$usesMagicTag && !$shouldSecureEntityLink) {
        return $template;
    }

    $CI->load->library('magic_login/Magic_login_service');
    $created = $CI->magic_login_service->create_token((int) $contact['id'], [
        'source'       => 'email',
        'context_type' => $relType !== '' ? $relType : 'portal',
        'context_id'   => $relId,
        'redirect_url' => $destination,
        'created_by'   => null,
    ]);

    if (!$created) {
        if ($usesMagicTag) {
            $mail->merge_fields['{magic_login_url}'] = '';
            $mail->merge_fields['{magic_login_button}'] = '';
            $mail->merge_fields['{magic_login_expiry}'] = '';
        }
        return $template;
    }

    if ($shouldSecureEntityLink) {
        $mail->merge_fields[$linkKey] = $created['url'];
    }

    if ($usesMagicTag) {
        $safeUrl = html_escape($created['url']);
        $mail->merge_fields['{magic_login_url}'] = $created['url'];
        $mail->merge_fields['{magic_login_button}'] = '<a href="' . $safeUrl . '">Secure Login</a>';
        $mail->merge_fields['{magic_login_expiry}'] = _dt($created['expires_at']);
    }

    return $template;
}
