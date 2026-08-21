<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Central authentication handler for Magic Login.
 * All authentication methods (email links, OTP, API) should use this service.
 */
class Magic_login_auth
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Authenticate a client contact using Perfex client session conventions.
     */
    public function authenticate_contact($contact)
    {
        if (empty($contact) || empty($contact['userid']) || empty($contact['id'])) {
            return false;
        }

        $this->CI->session->set_userdata([
            'client_user_id'   => (int) $contact['userid'],
            'contact_user_id'  => (int) $contact['id'],
            'client_logged_in' => true,
        ]);

        hooks()->do_action('after_contact_login', (int) $contact['userid']);

        return true;
    }
}
