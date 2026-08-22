<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Request extends ClientsController
{
    public function __construct()
    {
        parent::__construct();

        if (function_exists('magic_login_database_upgrade_required') && magic_login_database_upgrade_required()) {
            show_error('Magic Login is temporarily unavailable while a database upgrade is pending.', 503);
        }

        if (is_client_logged_in()) {
            redirect(site_url('clients'));
        }

        $this->load->library('magic_login/Magic_login_service');
        $this->load->library('magic_login/Magic_login_altcha');
        $this->load->library('email');
        $this->disableNavigation();
        $this->disableSubMenu();
    }

    public function index()
    {
        $this->data(['title' => 'Email magic login']);
        $this->view('magic_login_request');
        $this->layout();
    }

    public function send()
    {
        if (!$this->input->post()) {
            redirect(site_url('magic_login/request'));
        }

        if (function_exists('magic_login_altcha_enabled') && magic_login_altcha_enabled()
            && !$this->magic_login_altcha->verify((string) $this->input->post('altcha', false))) {
            if ($this->input->is_ajax_request()) {
                return $this->respond(false, 'The security check could not be verified. Please try again.', null, 422);
            }
            set_alert('warning', 'Please complete the security check and try again.');
            redirect(site_url('magic_login/request'));
        }

        $email = strtolower(trim((string) $this->input->post('email', true)));
        $ip = substr((string) $this->input->ip_address(), 0, 45);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($this->input->is_ajax_request()) {
                return $this->respond(false, 'Enter a valid email address.', null, 422);
            }
            set_alert('warning', 'Enter a valid email address.');
            redirect(site_url('magic_login/request'));
        }

        $cooldown = $this->cooldown_remaining($ip);
        if ($cooldown > 0 || !$this->within_rate_limit($ip, $email)) {
            $cooldown = max(1, $cooldown);
            if ($this->input->is_ajax_request()) {
                return $this->respond(false, 'Please wait before requesting another secure link.', null, 429, ['cooldown' => $cooldown]);
            }
            set_alert('warning', 'Please wait before requesting another secure link.');
            redirect(site_url('magic_login/request'));
        }

        // Record only a one-way email fingerprint; never place the submitted
        // address in the public request audit trail.
        $this->magic_login_service->audit('email_request', null, null, [
            'email_hash' => hash('sha256', $email),
        ]);

        $contact = $this->db->where('email', $email)
            ->where('active', 1)
            ->get(db_prefix() . 'contacts')->row_array();

        if ($contact && !empty($contact['userid']) && $this->within_contact_rate_limit((int) $contact['id'])) {
            $created = $this->magic_login_service->create_token((int) $contact['id'], [
                'source' => 'email',
                'expiry_minutes' => max(5, min(1440, (int) get_option('magic_login_default_expiry_minutes'))),
                'redirect_url' => 'clients',
            ]);
            if ($created) {
                $this->send_email($contact, $created['url'], $created['expires_at']);
            }
        }

        $message = 'If that email is registered, a secure login link will arrive shortly.';
        if ($this->input->is_ajax_request()) {
            return $this->respond(true, $message, null, 200, ['cooldown' => 60]);
        }
        set_alert('success', $message);
        redirect(site_url('magic_login/request'));
    }

    private function respond($ok, $message, $redirect = null, $status = 200, array $extra = [])
    {
        $csrfName = $this->security->get_csrf_token_name();
        $payload = array_merge([
            'ok'       => (bool) $ok,
            'message'  => (string) $message,
            'redirect' => $redirect,
            'csrf'     => [
                'name' => $csrfName,
                'hash' => $this->security->get_csrf_hash(),
            ],
        ], $extra);

        return $this->output
            ->set_status_header((int) $status)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload));
    }

    private function within_rate_limit($ip, $email)
    {
        if (substr_count($email, '@') !== 1 || strlen($ip) > 45) {
            return false;
        }

        $since = date('Y-m-d H:i:s', time() - 900);
        $auditTable = db_prefix() . 'magic_login_audit';
        if ($this->db->table_exists($auditTable)) {
            $count = $this->db->where('event', 'email_request')
                ->where('ip_address', $ip)
                ->where('created_at >=', $since)
                ->count_all_results($auditTable);
            return $count < 5;
        }

        return true;
    }

    private function cooldown_remaining($ip)
    {
        $auditTable = db_prefix() . 'magic_login_audit';
        if (!$this->db->table_exists($auditTable)) {
            return 0;
        }

        $row = $this->db->select('created_at')
            ->where('event', 'email_request')
            ->where('ip_address', $ip)
            ->order_by('id', 'desc')
            ->limit(1)
            ->get($auditTable)
            ->row_array();
        if (!$row || empty($row['created_at'])) {
            return 0;
        }

        return max(0, 60 - (time() - strtotime($row['created_at'])));
    }

    private function within_contact_rate_limit($contactId)
    {
        $tokensTable = db_prefix() . 'magic_login_tokens';
        if (!$this->db->table_exists($tokensTable)) {
            return false;
        }

        $count = $this->db
            ->where('contact_id', (int) $contactId)
            ->where('source', 'email')
            ->where('created_at >=', date('Y-m-d H:i:s', time() - 900))
            ->count_all_results($tokensTable);

        return $count < 3;
    }

    private function send_email(array $contact, $link, $expiresAt)
    {
        $fromEmail = trim((string) get_option('smtp_email')) ?: trim((string) get_option('companyemail'));
        if ($fromEmail === '') {
            $fromEmail = 'no-reply@airixmedia.com';
        }
        $fromName = trim((string) get_option('companyname')) ?: 'Airix Media';
        $subject = 'Your secure Airix Media login link';
        $message = '<p>Hello ' . html_escape(trim($contact['firstname'] . ' ' . $contact['lastname'])) . ',</p>'
            . '<p>Use the secure link below to open your Airix Media client portal:</p>'
            . '<p><a href="' . html_escape($link) . '">Open client portal</a></p>'
            . '<p>This link expires at <strong>' . html_escape(_dt($expiresAt)) . '</strong> and can be used once.</p>';

        // Authentication emails must leave immediately. Perfex queues normal
        // mail by default, which can delay a short-lived login link until it
        // has already expired.
        $this->load->config('email');
        $this->email->initialize();
        $this->email->set_newline(config_item('newline'));
        $this->email->set_crlf(config_item('crlf'));
        if ($this->email->smtp_auth === false && isset($this->email->phpmailer)) {
            // Perfex derives smtp_user from the sender address when the explicit
            // username is blank. Honour the supported my_email.php override for
            // unauthenticated local transports such as Mailpit.
            $this->email->phpmailer->SMTPAuth = false;
        }
        $this->email->clear(true);
        $this->email->from($fromEmail, $fromName);
        $this->email->to((string) $contact['email']);
        $this->email->subject($subject);
        $this->email->message($message);
        $sent = (bool) $this->email->send(true);
        if (!$sent) {
            log_message('error', 'Magic Login could not deliver a public email login request.');
            $mailerError = isset($this->email->phpmailer) && is_object($this->email->phpmailer)
                ? trim((string) $this->email->phpmailer->ErrorInfo)
                : '';
            error_log('Magic Login mail delivery failed' . ($mailerError !== '' ? ': ' . $mailerError : '.'));
        }
        return $sent;
    }
}
