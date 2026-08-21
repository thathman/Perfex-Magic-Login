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
        $this->data([
            'title' => 'Login with WhatsApp',
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
            && !$this->magic_login_altcha->verify((string) $this->input->post('altcha', false))) {
            set_alert('warning', 'Please complete the security check and try again.');
            redirect(site_url('magic_login/whatsapp'));
        }

        $phone = trim((string) $this->input->post('phone', true));
        $result = $this->magic_login_otp->request($phone);

        if (empty($result['ok'])) {
            if (isset($result['reason']) && $result['reason'] === 'rate_limited') {
                set_alert('warning', 'Too many login-code requests. Please try again later.');
            } else {
                set_alert('warning', 'Unable to request a login code right now.');
            }
            redirect(site_url('magic_login/whatsapp'));
        }

        $this->session->set_userdata('magic_login_pending_otp', $result['request_token']);
        set_alert('success', 'If this WhatsApp number is registered, a login code has been sent.');
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
                && !$this->magic_login_altcha->verify((string) $this->input->post('altcha', false))) {
                set_alert('warning', 'Please complete the security check and try again.');
                redirect(site_url('magic_login/whatsapp/verify'));
            }

            $code = trim((string) $this->input->post('code', true));
            $result = $this->magic_login_otp->verify($requestToken, $code);

            if (!empty($result['ok']) && !empty($result['contact'])) {
                $this->session->unset_userdata('magic_login_pending_otp');
                if ($this->magic_login_auth->authenticate_contact($result['contact'])) {
                    redirect(site_url('clients'));
                }
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
}
