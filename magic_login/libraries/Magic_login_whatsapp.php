<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Outbound WhatsApp transport.
 *
 * Default contract is a JSON POST with {to, message}. Integrations can adapt
 * the payload and headers through Perfex hooks without editing this module.
 */
class Magic_login_whatsapp
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function send_otp($phone, $code, $contact = null)
    {
        if ((int) get_option('magic_login_whatsapp_enabled') !== 1) {
            return false;
        }

        $url = trim((string) get_option('magic_login_whatsapp_api_url'));
        if ($url === '' || !preg_match('#^https://#i', $url)) {
            log_message('error', 'Magic Login WhatsApp API URL is missing or is not HTTPS.');
            return false;
        }

        $minutes = max(1, (int) get_option('magic_login_otp_expiry_minutes'));
        $template = trim((string) get_option('magic_login_whatsapp_message'));
        if ($template === '') {
            $template = 'Your {company} login code is {code}. It expires in {minutes} minutes.';
        }

        $company = trim((string) get_option('companyname'));
        $message = str_replace(
            ['{company}', '{code}', '{minutes}'],
            [$company !== '' ? $company : 'Perfex', (string) $code, (string) $minutes],
            $template
        );

        $context = [
            'phone'   => $phone,
            'contact' => $contact,
        ];

        $payload = hooks()->apply_filters('magic_login_whatsapp_payload', [
            'to'      => $phone,
            'message' => $message,
        ], $context);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $apiToken = trim((string) get_option('magic_login_whatsapp_api_token'));
        if ($apiToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiToken;
        }

        $headers = hooks()->apply_filters('magic_login_whatsapp_headers', $headers, $context);

        if (!function_exists('curl_init')) {
            log_message('error', 'Magic Login WhatsApp delivery requires the PHP cURL extension.');
            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            log_message('error', 'Magic Login WhatsApp request failed: ' . $curlError);
            return false;
        }

        $success = $status >= 200 && $status < 300;
        $success = (bool) hooks()->apply_filters('magic_login_whatsapp_delivery_success', $success, [
            'status'   => $status,
            'response' => $response,
            'context'  => $context,
        ]);

        if (!$success) {
            log_message('error', 'Magic Login WhatsApp API returned HTTP ' . $status . '.');
        }

        return $success;
    }
}
