<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Whatsapp extends ClientsController
{
    public function __construct()
    {
        parent::__construct();

        if ((int) get_option('magic_login_whatsapp_enabled') !== 1) {
            show_404();
        }

        if (is_client_logged_in()) {
            redirect(site_url('clients'));
        }

        $this->load->library('magic_login/Magic_login_otp');
        $this->load->library('magic_login/Magic_login_auth');
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
