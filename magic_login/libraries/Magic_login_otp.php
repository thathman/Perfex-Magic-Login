<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Magic_login_otp
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->library('magic_login/Magic_login_service');
        $this->CI->load->library('magic_login/Magic_login_whatsapp');
    }

    public function request($phone)
    {
        $phone = $this->normalize_phone($phone);
        $ip = substr((string) $this->CI->input->ip_address(), 0, 45);

        if (!$this->within_rate_limit($ip)) {
            return ['ok' => false, 'reason' => 'rate_limited'];
        }

        $contact = $phone !== false ? $this->find_contact_by_phone($phone) : null;
        $contactId = $contact ? (int) $contact['id'] : null;

        if ($contactId !== null && !$this->within_contact_rate_limit($contactId)) {
            return ['ok' => false, 'reason' => 'rate_limited'];
        }

        $requestToken = bin2hex(random_bytes(32));
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $maxAttempts = max(1, min(10, (int) get_option('magic_login_otp_max_attempts')));
        $minutes = max(1, min(30, (int) get_option('magic_login_otp_expiry_minutes')));
        $now = date('Y-m-d H:i:s');

        if ($contactId !== null) {
            $this->CI->db->where('contact_id', $contactId)
                ->where('used_at IS NULL', null, false)
                ->update(db_prefix() . 'magic_login_otps', ['used_at' => $now]);
        }

        $deliveryStatus = $contact ? 'pending' : 'ignored';
        $this->CI->db->insert(db_prefix() . 'magic_login_otps', [
            'contact_id'      => $contactId,
            'request_hash'    => hash('sha256', $requestToken),
            'code_hash'       => password_hash($code, PASSWORD_DEFAULT),
            'expires_at'      => date('Y-m-d H:i:s', time() + ($minutes * 60)),
            'attempts'        => 0,
            'max_attempts'    => $maxAttempts,
            'used_at'         => null,
            'requested_ip'    => $ip,
            'delivery_status' => $deliveryStatus,
            'sent_at'         => null,
            'created_at'      => $now,
        ]);

        if ($this->CI->db->affected_rows() !== 1) {
            return ['ok' => false, 'reason' => 'error'];
        }

        $otpId = (int) $this->CI->db->insert_id();
        $sent = false;

        if ($contact && $phone !== false) {
            $sent = $this->CI->magic_login_whatsapp->send_otp($phone, $code, $contact);
            $this->CI->db->where('id', $otpId)->update(db_prefix() . 'magic_login_otps', [
                'delivery_status' => $sent ? 'sent' : 'failed',
                'sent_at'         => $sent ? date('Y-m-d H:i:s') : null,
            ]);
        }

        $this->CI->magic_login_service->audit(
            $contact ? ($sent ? 'otp_sent' : 'otp_delivery_failed') : 'otp_request_unknown',
            null,
            $contactId,
            ['otp_id' => $otpId]
        );

        // Always return a request token for accepted requests. Unknown phone
        // numbers intentionally behave like real accounts to prevent enumeration.
        return [
            'ok'            => true,
            'request_token' => $requestToken,
            'delivery'      => $deliveryStatus,
        ];
    }

    public function verify($requestToken, $code)
    {
        $requestToken = trim((string) $requestToken);
        $code = trim((string) $code);

        if (!preg_match('/^[a-f0-9]{64}$/i', $requestToken) || !preg_match('/^[0-9]{6}$/', $code)) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        $hash = hash('sha256', $requestToken);
        $now = date('Y-m-d H:i:s');

        $row = $this->CI->db->where('request_hash', $hash)->get(db_prefix() . 'magic_login_otps')->row_array();
        if (!$row || !empty($row['used_at']) || strtotime($row['expires_at']) <= time()) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        $this->CI->db->where('id', (int) $row['id']);
        $this->CI->db->where('used_at IS NULL', null, false);
        $this->CI->db->where('expires_at >', $now);
        $this->CI->db->where('attempts < max_attempts', null, false);
        $this->CI->db->set('attempts', 'attempts + 1', false);
        $this->CI->db->update(db_prefix() . 'magic_login_otps');

        if ($this->CI->db->affected_rows() !== 1) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        if (!password_verify($code, $row['code_hash'])) {
            $this->CI->magic_login_service->audit('otp_failed', null, $row['contact_id'] !== null ? (int) $row['contact_id'] : null, [
                'otp_id' => (int) $row['id'],
            ]);
            return ['ok' => false, 'reason' => 'invalid'];
        }

        $this->CI->db->where('id', (int) $row['id']);
        $this->CI->db->where('used_at IS NULL', null, false);
        $this->CI->db->update(db_prefix() . 'magic_login_otps', ['used_at' => $now]);
        if ($this->CI->db->affected_rows() !== 1 || empty($row['contact_id'])) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        $contact = $this->CI->db
            ->where('id', (int) $row['contact_id'])
            ->where('active', 1)
            ->get(db_prefix() . 'contacts')
            ->row_array();

        if (!$contact || empty($contact['userid'])) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        $this->CI->magic_login_service->audit('otp_verified', null, (int) $contact['id'], [
            'otp_id' => (int) $row['id'],
        ]);

        return ['ok' => true, 'contact' => $contact];
    }

    public function normalize_phone($phone)
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return false;
        }

        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (substr($phone, 0, 2) === '00') {
            $phone = '+' . substr($phone, 2);
        }
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+' . $phone;
        }

        if (!preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)) {
            return false;
        }

        return $phone;
    }

    protected function find_contact_by_phone($normalized)
    {
        $digits = ltrim($normalized, '+');
        $candidates = [$normalized, $digits, '00' . $digits];

        $contact = $this->CI->db
            ->where('active', 1)
            ->where_in('phonenumber', $candidates)
            ->get(db_prefix() . 'contacts')
            ->row_array();

        if ($contact) {
            return $contact;
        }

        // Fallback for common formatting such as spaces, dashes and brackets.
        $rows = $this->CI->db
            ->select('id,userid,firstname,lastname,email,phonenumber,active')
            ->where('active', 1)
            ->where('phonenumber !=', '')
            ->limit(5000)
            ->get(db_prefix() . 'contacts')
            ->result_array();

        foreach ($rows as $row) {
            if ($this->normalize_phone($row['phonenumber']) === $normalized) {
                return $row;
            }
        }

        return null;
    }

    protected function within_rate_limit($ip)
    {
        $since = date('Y-m-d H:i:s', time() - 900);
        $count = $this->CI->db
            ->where('requested_ip', $ip)
            ->where('created_at >=', $since)
            ->count_all_results(db_prefix() . 'magic_login_otps');

        return $count < 10;
    }

    protected function within_contact_rate_limit($contactId)
    {
        $since = date('Y-m-d H:i:s', time() - 900);
        $count = $this->CI->db
            ->where('contact_id', (int) $contactId)
            ->where('created_at >=', $since)
            ->count_all_results(db_prefix() . 'magic_login_otps');

        return $count < 5;
    }
}
