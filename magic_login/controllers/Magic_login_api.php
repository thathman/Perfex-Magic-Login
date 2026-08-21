<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Magic_login_api extends App_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->output->set_content_type('application/json');

        if ((int) get_option('magic_login_api_enabled') !== 1) {
            $this->respond(['ok' => false, 'error' => 'API disabled'], 404);
        }

        if (!$this->authorized()) {
            $this->respond(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $this->load->library('magic_login/Magic_login_service');
        $this->load->library('magic_login/Magic_login_otp');
    }

    public function create_link()
    {
        $this->require_post();
        $data = $this->request_data();
        $contact = $this->resolve_contact($data);

        if (!$contact) {
            $this->respond(['ok' => false, 'error' => 'Contact not found'], 404);
        }

        $created = $this->magic_login_service->create_token((int) $contact['id'], [
            'expiry_minutes' => isset($data['expiry_minutes']) ? (int) $data['expiry_minutes'] : null,
            'source'         => 'api',
            'context_type'   => isset($data['context_type']) ? $data['context_type'] : 'portal',
            'context_id'     => isset($data['context_id']) ? $data['context_id'] : null,
            'redirect_url'   => isset($data['redirect_url']) ? $data['redirect_url'] : 'clients',
            'created_by'     => null,
        ]);

        if (!$created) {
            $this->respond(['ok' => false, 'error' => 'Unable to create login link'], 500);
        }

        $this->respond([
            'ok'         => true,
            'login_url'  => $created['url'],
            'expires_at' => $created['expires_at'],
        ]);
    }

    public function request_otp()
    {
        $this->require_post();

        if ((int) get_option('magic_login_whatsapp_enabled') !== 1) {
            $this->respond(['ok' => false, 'error' => 'WhatsApp login disabled'], 409);
        }

        $data = $this->request_data();
        $phone = isset($data['phone']) ? $data['phone'] : '';
        $result = $this->magic_login_otp->request($phone);

        if (empty($result['ok'])) {
            $status = isset($result['reason']) && $result['reason'] === 'rate_limited' ? 429 : 503;
            $this->respond(['ok' => false, 'error' => $status === 429 ? 'Rate limited' : 'Unable to request OTP'], $status);
        }

        $this->respond([
            'ok'         => true,
            'request_id' => $result['request_token'],
            'message'    => 'If the number is registered, a code has been sent.',
        ], 202);
    }

    public function verify_otp()
    {
        $this->require_post();
        $data = $this->request_data();

        $requestId = isset($data['request_id']) ? $data['request_id'] : '';
        $code = isset($data['code']) ? $data['code'] : '';
        $result = $this->magic_login_otp->verify($requestId, $code);

        if (empty($result['ok']) || empty($result['contact'])) {
            $this->respond(['ok' => false, 'error' => 'Invalid or expired code'], 401);
        }

        $created = $this->magic_login_service->create_token((int) $result['contact']['id'], [
            'source'         => 'whatsapp',
            'context_type'   => isset($data['context_type']) ? $data['context_type'] : 'portal',
            'context_id'     => isset($data['context_id']) ? $data['context_id'] : null,
            'redirect_url'   => isset($data['redirect_url']) ? $data['redirect_url'] : 'clients',
            'expiry_minutes' => isset($data['expiry_minutes']) ? (int) $data['expiry_minutes'] : null,
            'created_by'     => null,
        ]);

        if (!$created) {
            $this->respond(['ok' => false, 'error' => 'Unable to create login link'], 500);
        }

        $this->respond([
            'ok'         => true,
            'login_url'  => $created['url'],
            'expires_at' => $created['expires_at'],
        ]);
    }

    public function revoke()
    {
        $this->require_post();
        $data = $this->request_data();
        $id = isset($data['token_id']) ? (int) $data['token_id'] : 0;

        if ($id < 1) {
            $this->respond(['ok' => false, 'error' => 'token_id is required'], 422);
        }

        $now = date('Y-m-d H:i:s');
        $this->db->where('id', $id);
        $this->db->where('used_at IS NULL', null, false);
        $this->db->where('revoked_at IS NULL', null, false);
        $this->db->update(db_prefix() . 'magic_login_tokens', [
            'revoked_at' => $now,
            'revoked_by' => null,
        ]);

        if ($this->db->affected_rows() !== 1) {
            $this->respond(['ok' => false, 'error' => 'Token not active or not found'], 409);
        }

        $row = $this->db->where('id', $id)->get(db_prefix() . 'magic_login_tokens')->row_array();
        $this->magic_login_service->audit('revoked_api', $id, $row ? (int) $row['contact_id'] : null);

        $this->respond(['ok' => true]);
    }

    private function authorized()
    {
        $storedHash = trim((string) get_option('magic_login_api_key_hash'));
        if ($storedHash === '') {
            return false;
        }

        $header = trim((string) $this->input->get_request_header('Authorization', true));
        $key = '';

        if (stripos($header, 'Bearer ') === 0) {
            $key = trim(substr($header, 7));
        }

        if ($key === '') {
            $key = trim((string) $this->input->get_request_header('X-Magic-Login-Key', true));
        }

        if ($key === '') {
            return false;
        }

        return hash_equals($storedHash, hash('sha256', $key));
    }

    private function resolve_contact(array $data)
    {
        $query = $this->db->where('active', 1);

        if (!empty($data['contact_id'])) {
            $query->where('id', (int) $data['contact_id']);
        } elseif (!empty($data['email'])) {
            $query->where('email', trim((string) $data['email']));
        } else {
            return null;
        }

        $contact = $query->get(db_prefix() . 'contacts')->row_array();
        return $contact && !empty($contact['userid']) ? $contact : null;
    }

    private function request_data()
    {
        $raw = trim((string) $this->input->raw_input_stream);
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $post = $this->input->post(null, true);
        return is_array($post) ? $post : [];
    }

    private function require_post()
    {
        if (strtoupper((string) $this->input->method()) !== 'POST') {
            $this->respond(['ok' => false, 'error' => 'Method not allowed'], 405);
        }
    }

    private function respond(array $payload, $status = 200)
    {
        $this->output
            ->set_status_header((int) $status)
            ->set_output(json_encode($payload));
        $this->output->_display();
        exit;
    }
}
