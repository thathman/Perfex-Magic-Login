<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Token lifecycle and redirect service for Magic Login.
 */
class Magic_login_service
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function create_token($contactId, $options = [])
    {
        $contactId = (int) $contactId;
        $contact = $this->CI->db
            ->where('id', $contactId)
            ->where('active', 1)
            ->get(db_prefix() . 'contacts')
            ->row_array();

        if (!$contact || empty($contact['userid'])) {
            return false;
        }

        $defaultMinutes = (int) get_option('magic_login_default_expiry_minutes');
        if ($defaultMinutes < 1) {
            $defaultMinutes = 60;
        }

        $minutes = isset($options['expiry_minutes']) ? (int) $options['expiry_minutes'] : $defaultMinutes;
        $minutes = max(1, min(10080, $minutes));

        $source = isset($options['source']) ? strtolower(trim((string) $options['source'])) : 'manual';
        $allowedSources = ['manual', 'email', 'api', 'whatsapp'];
        if (!in_array($source, $allowedSources, true)) {
            $source = 'manual';
        }

        $contextType = isset($options['context_type']) ? strtolower(trim((string) $options['context_type'])) : null;
        if ($contextType !== null && $contextType !== '') {
            $contextType = preg_replace('/[^a-z0-9_-]/', '', $contextType);
            $contextType = substr($contextType, 0, 50);
        } else {
            $contextType = null;
        }

        $contextId = isset($options['context_id']) && $options['context_id'] !== ''
            ? (int) $options['context_id']
            : null;

        $redirectPath = $this->normalize_destination(isset($options['redirect_url']) ? $options['redirect_url'] : 'clients');

        $createdBy = array_key_exists('created_by', $options) ? $options['created_by'] : null;
        if ($createdBy !== null) {
            $createdBy = (int) $createdBy;
            if ($createdBy < 1) {
                $createdBy = null;
            }
        }

        try {
            $plainToken = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            log_message('error', 'Magic Login token generation failed: ' . $e->getMessage());
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + ($minutes * 60));
        $tokenHash = hash('sha256', $plainToken);

        $this->CI->db->insert(db_prefix() . 'magic_login_tokens', [
            'contact_id'      => $contactId,
            'token_hash'      => $tokenHash,
            'expires_at'      => $expiresAt,
            'used_at'         => null,
            'revoked_at'      => null,
            'revoked_by'      => null,
            'created_by'      => $createdBy,
            'source'          => $source,
            'context_type'    => $contextType,
            'context_id'      => $contextId,
            'redirect_path'   => $redirectPath,
            'used_ip'         => null,
            'used_user_agent' => null,
            'created_at'      => $now,
        ]);

        if ($this->CI->db->affected_rows() !== 1) {
            $error = $this->CI->db->error();
            log_message('error', 'Magic Login token insert failed: ' . (isset($error['message']) ? $error['message'] : 'unknown database error'));
            return false;
        }

        $id = (int) $this->CI->db->insert_id();
        $this->audit('created', $id, $contactId, [
            'source'       => $source,
            'context_type' => $contextType,
            'context_id'   => $contextId,
        ]);

        return [
            'id'         => $id,
            'token'      => $plainToken,
            'url'        => $this->build_link($plainToken),
            'expires_at' => $expiresAt,
            'contact'    => $contact,
        ];
    }

    public function build_link($plainToken)
    {
        return site_url('magic_login/link/' . rawurlencode((string) $plainToken));
    }

    public function consume_token($plainToken)
    {
        $plainToken = trim((string) $plainToken);
        if ($plainToken === '' || !preg_match('/^[a-f0-9]{64}$/i', $plainToken)) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        $hash = hash('sha256', $plainToken);
        $now  = date('Y-m-d H:i:s');
        $ip   = $this->CI->input->ip_address();
        $ua   = substr((string) $this->CI->input->user_agent(), 0, 255);

        $this->CI->db->where('token_hash', $hash);
        $this->CI->db->where('used_at IS NULL', null, false);
        $this->CI->db->where('revoked_at IS NULL', null, false);
        $this->CI->db->where('expires_at >', $now);
        $this->CI->db->update(db_prefix() . 'magic_login_tokens', [
            'used_at'         => $now,
            'used_ip'         => $ip,
            'used_user_agent' => $ua,
        ]);

        if ($this->CI->db->affected_rows() !== 1) {
            $row = $this->CI->db->where('token_hash', $hash)->get(db_prefix() . 'magic_login_tokens')->row_array();
            if (!$row) {
                return ['ok' => false, 'reason' => 'invalid'];
            }
            if (!empty($row['revoked_at'])) {
                return ['ok' => false, 'reason' => 'revoked'];
            }
            if (!empty($row['used_at'])) {
                return ['ok' => false, 'reason' => 'used'];
            }
            if (strtotime($row['expires_at']) <= time()) {
                return ['ok' => false, 'reason' => 'expired'];
            }
            return ['ok' => false, 'reason' => 'invalid'];
        }

        $row = $this->CI->db->where('token_hash', $hash)->get(db_prefix() . 'magic_login_tokens')->row_array();
        if (!$row) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        $this->audit('used', (int) $row['id'], (int) $row['contact_id'], [
            'source'       => isset($row['source']) ? $row['source'] : null,
            'context_type' => isset($row['context_type']) ? $row['context_type'] : null,
            'context_id'   => isset($row['context_id']) ? $row['context_id'] : null,
        ]);

        return ['ok' => true, 'row' => $row];
    }

    public function revoke_token($id, $staffId)
    {
        $id = (int) $id;
        $staffId = (int) $staffId;
        if ($id < 1 || $staffId < 1) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->CI->db->where('id', $id);
        $this->CI->db->where('used_at IS NULL', null, false);
        $this->CI->db->where('revoked_at IS NULL', null, false);
        $this->CI->db->update(db_prefix() . 'magic_login_tokens', [
            'revoked_at' => $now,
            'revoked_by' => $staffId,
        ]);

        if ($this->CI->db->affected_rows() !== 1) {
            return false;
        }

        $row = $this->CI->db->where('id', $id)->get(db_prefix() . 'magic_login_tokens')->row_array();
        $this->audit('revoked', $id, $row ? (int) $row['contact_id'] : null, [
            'revoked_by' => $staffId,
        ]);

        return true;
    }

    public function destination_url($row)
    {
        $path = is_array($row) && !empty($row['redirect_path']) ? $row['redirect_path'] : 'clients';
        $path = $this->normalize_destination($path);
        return site_url($path);
    }

    public function normalize_destination($destination)
    {
        $destination = trim((string) $destination);
        if ($destination === '') {
            return 'clients';
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $destination) || strpos($destination, '\\') !== false) {
            return 'clients';
        }

        if (strpos($destination, '//') === 0) {
            return 'clients';
        }

        if (preg_match('#^https?://#i', $destination)) {
            $base = parse_url(site_url());
            $target = parse_url($destination);
            if (!$base || !$target || empty($base['host']) || empty($target['host'])) {
                return 'clients';
            }

            $baseScheme = strtolower(isset($base['scheme']) ? $base['scheme'] : 'http');
            $targetScheme = strtolower(isset($target['scheme']) ? $target['scheme'] : 'http');
            $basePort = isset($base['port']) ? (int) $base['port'] : ($baseScheme === 'https' ? 443 : 80);
            $targetPort = isset($target['port']) ? (int) $target['port'] : ($targetScheme === 'https' ? 443 : 80);

            if (strtolower($base['host']) !== strtolower($target['host'])
                || $baseScheme !== $targetScheme
                || $basePort !== $targetPort) {
                return 'clients';
            }

            $basePath = trim(isset($base['path']) ? $base['path'] : '', '/');
            $targetPath = ltrim(isset($target['path']) ? $target['path'] : '', '/');

            if ($basePath !== '') {
                if ($targetPath === $basePath) {
                    $targetPath = '';
                } elseif (strpos($targetPath, $basePath . '/') === 0) {
                    $targetPath = substr($targetPath, strlen($basePath) + 1);
                } else {
                    return 'clients';
                }
            }

            $destination = $targetPath;
            if (!empty($target['query'])) {
                $destination .= '?' . $target['query'];
            }
        } else {
            $destination = ltrim($destination, '/');
        }

        if ($destination === '' || strpos($destination, '../') !== false || strpos($destination, '..\\') !== false) {
            return 'clients';
        }

        return substr($destination, 0, 500);
    }

    public function audit($event, $tokenId = null, $contactId = null, $metadata = [])
    {
        $table = db_prefix() . 'magic_login_audit';
        if (!$this->CI->db->table_exists($table)) {
            return;
        }

        $event = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $event));
        if ($event === '') {
            return;
        }

        $this->CI->db->insert($table, [
            'token_id'   => $tokenId !== null ? (int) $tokenId : null,
            'contact_id' => $contactId !== null ? (int) $contactId : null,
            'event'      => substr($event, 0, 50),
            'ip_address' => substr((string) $this->CI->input->ip_address(), 0, 45),
            'user_agent' => substr((string) $this->CI->input->user_agent(), 0, 255),
            'metadata'   => !empty($metadata) ? json_encode($metadata) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
