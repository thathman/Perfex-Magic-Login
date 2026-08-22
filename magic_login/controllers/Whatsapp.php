<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Whatsapp extends ClientsController
{
    public function __construct()
    {
        parent::__construct();

        if (function_exists('magic_login_database_upgrade_required') && magic_login_database_upgrade_required()) {
            show_error('Magic Login is temporarily unavailable while a database upgrade is pending.', 503);
        }

        if ((int) get_option('magic_login_whatsapp_enabled') !== 1) {
            show_404();
        }

        if (is_client_logged_in()) {
            redirect(site_url('clients'));
        }

        $this->load->library('magic_login/Magic_login_otp');
        $this->load->library('magic_login/Magic_login_auth');
        $this->load->library('magic_login/Magic_login_altcha');
        $this->disableNavigation();
        $this->disableSubMenu();
    }

    public function index()
    {
        $countries = array_values(array_filter(get_all_countries(), function ($country) {
            return !empty($country['country_id']) && preg_replace('/\D+/', '', (string) ($country['calling_code'] ?? '')) !== '';
        }));
        $selectedCountry = (int) get_option('customer_default_country');
        if ($selectedCountry < 1) {
            foreach ($countries as $country) {
                if (strtoupper((string) ($country['iso2'] ?? '')) === 'NG') {
                    $selectedCountry = (int) $country['country_id'];
                    break;
                }
            }
        }

        $this->data([
            'title'            => 'Login with WhatsApp',
            'countries'        => $countries,
            'selected_country' => $selectedCountry,
        ]);
        $this->view('whatsapp_login');
        $this->layout();
    }

    public function request()
    {
        if (!$this->input->post()) {
            redirect(site_url('magic_login/whatsapp'));
        }

        if (function_exists('magic_login_altcha_enabled') && magic_login_altcha_enabled()
            && !$this->magic_login_altcha->verify((string) $this->input->post('magic_login_altcha', false))) {
            if ($this->input->is_ajax_request()) {
                return $this->respond(false, 'The security check could not be verified. Please try again.', null, 422);
            }
            set_alert('warning', 'Please complete the security check and try again.');
            redirect(site_url('magic_login/whatsapp'));
        }

        $phone = $this->magic_login_otp->normalize_phone_for_country(
            (string) $this->input->post('phone', true),
            (int) $this->input->post('country_id')
        );
        if ($phone === false) {
            if ($this->input->is_ajax_request()) {
                return $this->respond(false, 'Enter a valid WhatsApp number for the selected country.', null, 422);
            }
            set_alert('warning', 'Enter a valid WhatsApp number for the selected country.');
            redirect(site_url('magic_login/whatsapp'));
        }

        $result = $this->magic_login_otp->request($phone);

        if (empty($result['ok'])) {
            $reason = isset($result['reason']) ? $result['reason'] : 'error';
            $message = $reason === 'cooldown'
                ? 'Please wait before requesting another login code.'
                : ($reason === 'rate_limited'
                    ? 'Too many login-code requests. Please wait before trying again.'
                    : 'Unable to request a login code right now.');
            if ($this->input->is_ajax_request()) {
                $extra = $reason === 'cooldown' ? ['cooldown' => max(1, (int) ($result['retry_after'] ?? 60))] : [];
                return $this->respond(false, $message, null, 429, $extra);
            }
            if (isset($result['reason']) && $result['reason'] === 'rate_limited') {
                set_alert('warning', 'Too many login-code requests. Please try again later.');
            } else {
                set_alert('warning', 'Unable to request a login code right now.');
            }
            redirect(site_url('magic_login/whatsapp'));
        }

        $this->session->set_userdata('magic_login_pending_otp', $result['request_token']);
        $message = 'If this WhatsApp number is registered, a login code has been sent.';
        if ($this->input->is_ajax_request()) {
            return $this->respond(true, $message, site_url('magic_login/whatsapp/verify'), 200, ['cooldown' => 60]);
        }
        set_alert('success', $message);
        redirect(site_url('magic_login/whatsapp/verify'));
    }

    public function verify()
    {
        $requestToken = (string) $this->session->userdata('magic_login_pending_otp');
        if ($requestToken === '') {
            redirect(site_url('magic_login/whatsapp'));
        }

        if ($this->input->post()) {
            if (function_exists('magic_login_altcha_enabled') && magic_login_altcha_enabled()
                && !$this->magic_login_altcha->verify((string) $this->input->post('magic_login_altcha', false))) {
                if ($this->input->is_ajax_request()) {
                    return $this->respond(false, 'The security check could not be verified. Please try again.', null, 422);
                }
                set_alert('warning', 'Please complete the security check and try again.');
                redirect(site_url('magic_login/whatsapp/verify'));
            }

            $code = trim((string) $this->input->post('code', true));
            $result = $this->magic_login_otp->verify($requestToken, $code);

            if (!empty($result['ok']) && !empty($result['contact'])) {
                $this->session->unset_userdata('magic_login_pending_otp');
                if ($this->magic_login_auth->authenticate_contact($result['contact'])) {
                    if ($this->input->is_ajax_request()) {
                        return $this->respond(true, 'Code verified. Opening your workspace…', site_url('clients'));
                    }
                    redirect(site_url('clients'));
                }
            }

            if ($this->input->is_ajax_request()) {
                return $this->respond(false, 'The login code is invalid or has expired.', null, 422);
            }
            set_alert('warning', 'The login code is invalid or has expired.');
            redirect(site_url('magic_login/whatsapp/verify'));
        }

        $this->data([
            'title' => 'Verify WhatsApp Code',
        ]);
        $this->view('whatsapp_verify');
        $this->layout();
    }

    private function respond($ok, $message, $redirect = null, $status = 200, array $extra = [])
    {
        $payload = array_merge([
            'ok'       => (bool) $ok,
            'message'  => (string) $message,
            'redirect' => $redirect,
            'csrf'     => [
                'name' => $this->security->get_csrf_token_name(),
                'hash' => $this->security->get_csrf_hash(),
            ],
        ], $extra);

        return $this->output
            ->set_status_header((int) $status)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload));
    }
}
