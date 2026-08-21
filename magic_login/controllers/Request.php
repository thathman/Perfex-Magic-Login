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
            set_alert('warning', 'Please complete the security check and try again.');
            redirect(site_url('magic_login/request'));
        }

        $email = strtolower(trim((string) $this->input->post('email', true)));
        $ip = substr((string) $this->input->ip_address(), 0, 45);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$this->within_rate_limit($ip, $email)) {
            set_alert('success', 'If that email is registered, a secure login link will arrive shortly.');
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

        if ($contact && !empty($contact['userid'])) {
            $created = $this->magic_login_service->create_token((int) $contact['id'], [
                'source' => 'email',
                'expiry_minutes' => max(5, min(1440, (int) get_option('magic_login_default_expiry_minutes'))),
                'redirect_url' => 'clients',
            ]);
            if ($created) {
                $this->send_email($contact, $created['url'], $created['expires_at']);
            }
        }

        set_alert('success', 'If that email is registered, a secure login link will arrive shortly.');
        redirect(site_url('magic_login/request'));
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

    private function send_email(array $contact, $link, $expiresAt)
    {
        $fromEmail = trim((string) get_option('smtp_email')) ?: trim((string) get_option('companyemail'));
        if ($fromEmail === '') {
            $fromEmail = 'no-reply@' . (parse_url(site_url(), PHP_URL_HOST) ?: 'localhost');
        }
        $fromName = trim((string) get_option('companyname')) ?: 'Airix Media';

        $this->email->clear(true);
        $this->email->from($fromEmail, $fromName);
        $this->email->to((string) $contact['email']);
        $this->email->subject('Your secure Airix Media login link');
        $this->email->message('<p>Hello ' . html_escape(trim($contact['firstname'] . ' ' . $contact['lastname'])) . ',</p>'
            . '<p>Use the secure link below to open your Airix Media client portal:</p>'
            . '<p><a href="' . html_escape($link) . '">Open client portal</a></p>'
            . '<p>This link expires at <strong>' . html_escape(_dt($expiresAt)) . '</strong> and can be used once.</p>');
        return (bool) $this->email->send();
    }
}
