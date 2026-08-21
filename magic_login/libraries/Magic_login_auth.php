<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Central authentication handler for Magic Login.
 *
 * Passwordless methods authenticate only active client contacts and mirror
 * Perfex client login lifecycle hooks/session metadata as closely as possible
 * without validating a password.
 */
class Magic_login_auth
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Authenticate an active client contact.
     *
     * @param array|object $contact
     * @return bool
     */
    public function authenticate_contact($contact)
    {
        if (is_object($contact)) {
            $contact = (array) $contact;
        }

        if (!is_array($contact)
            || empty($contact['id'])
            || empty($contact['userid'])
            || (isset($contact['active']) && (int) $contact['active'] !== 1)) {
            return false;
        }

        $contactId = (int) $contact['id'];
        $clientId  = (int) $contact['userid'];
        $email     = isset($contact['email']) ? (string) $contact['email'] : '';

        hooks()->do_action('before_client_login', [
            'email'           => $email,
            'userid'          => $clientId,
            'contact_user_id' => $contactId,
        ]);

        $this->CI->session->set_userdata([
            'client_user_id'   => $clientId,
            'contact_user_id'  => $contactId,
            'client_logged_in' => true,
        ]);

        $now = date('Y-m-d H:i:s');
        $ip  = $this->CI->input->ip_address();

        $this->CI->db->where('id', $contactId)->update(db_prefix() . 'contacts', [
            'last_ip'    => $ip,
            'last_login' => $now,
        ]);

        log_activity('User Successfully Logged In via Magic Login [User Id: ' . $contactId . ', Is Staff Member: No, IP: ' . $ip . ']');

        try {
            $this->CI->load->model('announcements_model');
            if (isset($this->CI->announcements_model)) {
                $this->CI->announcements_model->set_announcements_as_read_except_last_one($contactId);
            }
        } catch (Throwable $e) {
            log_message('debug', 'Magic Login announcements sync skipped: ' . $e->getMessage());
        }

        hooks()->do_action('after_contact_login');

        return true;
    }
}
