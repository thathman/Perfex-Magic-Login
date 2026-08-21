<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Link extends App_Controller
{
    public function __construct()
    {
        parent::__construct();

        if (function_exists('magic_login_database_upgrade_required') && magic_login_database_upgrade_required()) {
            show_error('Magic Login is temporarily unavailable while a database upgrade is pending.', 503);
        }

        $this->load->library('magic_login/Magic_login_service');
        $this->load->library('magic_login/Magic_login_auth');
    }

    public function index($token = '')
    {
        $token = trim((string) $token);
        if ($token === '') {
            $token = trim((string) $this->input->get('token', true));
        }

        $result = $this->magic_login_service->consume_token($token);
        if (empty($result['ok'])) {
            $this->show_token_error(isset($result['reason']) ? $result['reason'] : 'invalid');
            return;
        }

        $row = $result['row'];
        $contact = $this->db
            ->where('id', (int) $row['contact_id'])
            ->where('active', 1)
            ->get(db_prefix() . 'contacts')
            ->row_array();

        if (!$contact || empty($contact['userid'])) {
            $this->magic_login_service->audit('failed_contact', (int) $row['id'], (int) $row['contact_id']);
            show_error('This login link can no longer be used.', 410);
            return;
        }

        if (!$this->magic_login_auth->authenticate_contact($contact)) {
            $this->magic_login_service->audit('failed_auth', (int) $row['id'], (int) $row['contact_id']);
            show_error('Unable to complete login.', 500);
            return;
        }

        redirect($this->magic_login_service->destination_url($row));
    }

    private function show_token_error($reason)
    {
        switch ($reason) {
            case 'expired':
                show_error('Magic login link expired.', 410);
                break;
            case 'used':
                show_error('Magic login link already used.', 410);
                break;
            case 'revoked':
                show_error('Magic login link has been revoked.', 410);
                break;
            default:
                show_error('Invalid magic login link.', 404);
                break;
        }
    }
}
