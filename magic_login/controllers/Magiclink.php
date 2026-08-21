<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Magiclink extends CI_Controller
{
    public function index()
    {
        $token = trim((string)$this->input->get('token', true));
        if ($token === '') {
            show_error('Invalid token', 400);
            return;
        }

        $hash = hash('sha256', $token);
        $row = $this->db->where('token_hash', $hash)->get(db_prefix() . 'magic_login_tokens')->row_array();

        if (!$row) {
            show_error('Invalid magic link.', 404);
            return;
        }

        if (!empty($row['used_at'])) {
            show_error('Magic link already used.', 410);
            return;
        }

        if (strtotime($row['expires_at']) < time()) {
            show_error('Magic link expired.', 410);
            return;
        }

        $contact = $this->db->where('id', (int)$row['contact_id'])->where('active', 1)->get(db_prefix() . 'contacts')->row_array();
        if (!$contact) {
            show_error('Contact not found.', 404);
            return;
        }

        $this->db->where('id', (int)$row['id'])->update(db_prefix() . 'magic_login_tokens', ['used_at' => date('Y-m-d H:i:s')]);

        $this->session->set_userdata([
            'client_user_id'   => (int)$contact['userid'],
            'contact_user_id'  => (int)$contact['id'],
            'client_logged_in' => true,
        ]);

        $next = trim((string)$this->input->get('next', true));
        if ($next === '' && !empty($row['redirect_path'])) {
            $next = site_url((string)$row['redirect_path']);
        }
        if ($next !== '' && preg_match('#^https?://#i', $next)) {
            $base = rtrim(site_url(), '/');
            if (stripos($next, $base) === 0) {
                redirect($next);
                return;
            }
        }

        redirect(site_url('clients'));
    }
}
