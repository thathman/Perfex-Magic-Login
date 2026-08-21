<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Small self-hosted ALTCHA v1-compatible proof-of-work implementation.
 * The challenge is signed with a server-only key and expires in ten minutes.
 */
class Magic_login_altcha
{
    private const MAX_NUMBER = 500000;
    private const TTL = 600;

    public function challenge()
    {
        $salt = bin2hex(random_bytes(16)) . '?expires=' . (time() + self::TTL);
        $number = random_int(0, self::MAX_NUMBER);
        $challenge = hash('sha256', $salt . $number);

        return [
            'algorithm'  => 'SHA-256',
            'challenge'  => $challenge,
            'maxnumber'  => self::MAX_NUMBER,
            'salt'       => $salt,
            'signature'  => hash_hmac('sha256', $challenge, $this->key()),
        ];
    }

    public function verify($payload)
    {
        $decoded = base64_decode(trim((string) $payload), true);
        if (!$decoded) {
            return false;
        }

        $data = json_decode($decoded, true);
        if (!is_array($data)
            || ($data['algorithm'] ?? '') !== 'SHA-256'
            || !isset($data['challenge'], $data['number'], $data['salt'], $data['signature'])
            || !is_string($data['challenge'])
            || !is_string($data['salt'])
            || !is_string($data['signature'])
            || !is_int($data['number'])
            || $data['number'] < 0
            || $data['number'] > self::MAX_NUMBER) {
            return false;
        }

        $parts = parse_url('https://altcha.local/?' . (strpos($data['salt'], '?') !== false ? explode('?', $data['salt'], 2)[1] : ''));
        $expires = isset($parts['query']) ? null : null;
        parse_str((string) ($parts['query'] ?? ''), $params);
        if (empty($params['expires']) || (int) $params['expires'] < time()) {
            return false;
        }

        $expectedChallenge = hash('sha256', $data['salt'] . $data['number']);
        $expectedSignature = hash_hmac('sha256', $expectedChallenge, $this->key());
        return hash_equals($expectedChallenge, $data['challenge'])
            && hash_equals($expectedSignature, $data['signature']);
    }

    private function key()
    {
        $key = trim((string) get_option('magic_login_altcha_secret'));
        if ($key === '') {
            $key = bin2hex(random_bytes(32));
            update_option('magic_login_altcha_secret', $key);
        }
        return $key;
    }
}
