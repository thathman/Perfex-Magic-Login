<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Magic_login extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        if (function_exists('magic_login_database_upgrade_required') && magic_login_database_upgrade_required()) {
            set_alert('warning', 'Magic Login requires a database upgrade. Complete Setup → Modules → Upgrade Database before using the module.');
            redirect(admin_url('modules'));
        }

        $this->load->library('magic_login/Magic_login_service');
    }

    public function index()
    {
        if (!staff_can('view', 'magic_login') && !is_admin()) {
            access_denied('magic_login');
        }

        $data['contacts'] = $this->db->select('c.id,c.userid,c.firstname,c.lastname,c.email,cl.company')
            ->from(db_prefix() . 'contacts c')
            ->join(db_prefix() . 'clients cl', 'cl.userid=c.userid', 'left')
            ->where('c.active', 1)
            ->order_by('c.id', 'DESC')
            ->limit(500)
            ->get()->result_array();

        $data['tokens'] = $this->db->select('t.*,c.email,c.firstname,c.lastname,cl.company')
            ->from(db_prefix() . 'magic_login_tokens t')
            ->join(db_prefix() . 'contacts c', 'c.id=t.contact_id', 'left')
            ->join(db_prefix() . 'clients cl', 'cl.userid=c.userid', 'left')
            ->order_by('t.id', 'DESC')
            ->limit(200)
            ->get()->result_array();

        $auditTable = db_prefix() . 'magic_login_audit';
        $data['audit'] = $this->db->table_exists($auditTable)
            ? $this->db->order_by('id', 'DESC')->limit(100)->get($auditTable)->result_array()
            : [];

        $updatesTable = db_prefix() . 'magic_login_updates';
        $data['updates'] = $this->db->table_exists($updatesTable)
            ? $this->db->order_by('id', 'DESC')->limit(50)->get($updatesTable)->result_array()
            : [];

        $data['endpoint_options'] = $this->endpoint_options();
        $data['settings'] = [
            'default_expiry_minutes'   => max(1, (int) get_option('magic_login_default_expiry_minutes')),
            'auto_secure_email_links'  => (int) get_option('magic_login_auto_secure_email_links'),
            'disable_password_login'   => (int) get_option('magic_login_disable_password_login'),
            'altcha_enabled'           => (int) get_option('magic_login_altcha_enabled'),
            'whatsapp_enabled'         => (int) get_option('magic_login_whatsapp_enabled'),
            'whatsapp_api_url'         => (string) get_option('magic_login_whatsapp_api_url'),
            'whatsapp_token_set'       => trim((string) get_option('magic_login_whatsapp_api_token')) !== '',
            'whatsapp_message'         => (string) get_option('magic_login_whatsapp_message'),
            'otp_expiry_minutes'       => max(1, (int) get_option('magic_login_otp_expiry_minutes')),
            'otp_max_attempts'         => max(1, (int) get_option('magic_login_otp_max_attempts')),
            'api_enabled'              => (int) get_option('magic_login_api_enabled'),
            'api_key_set'              => trim((string) get_option('magic_login_api_key_hash')) !== '',
            'update_policy'            => (string) get_option('magic_login_update_policy'),
            'last_update_status'       => (string) get_option('magic_login_last_update_status'),
        ];
        $data['new_api_key'] = $this->session->flashdata('magic_login_new_api_key');
        $data['module_version'] = defined('MAGIC_LOGIN_VERSION') ? MAGIC_LOGIN_VERSION : 'unknown';

        $data['title'] = 'Magic Login';
        $this->load->view('magic_login/manage', $data);
    }

    public function create()
    {
        if (!staff_can('create', 'magic_login') && !is_admin()) {
            access_denied('magic_login');
        }

        if (!$this->input->post()) {
            show_404();
        }

        $contactId = (int) $this->input->post('contact_id');
        $hours = max(1, min(168, (int) $this->input->post('hours')));
        $redirectPath = $this->resolve_endpoint_path(
            (string) $this->input->post('portal_endpoint', true),
            (string) $this->input->post('custom_endpoint', true)
        );
        $sendEmail = (int) $this->input->post('send_email') === 1;

        $created = $this->magic_login_service->create_token($contactId, [
            'expiry_minutes' => $hours * 60,
            'source'         => 'manual',
            'redirect_url'   => $redirectPath,
            'created_by'     => (int) get_staff_user_id(),
        ]);

        if (!$created) {
            set_alert('warning', 'Failed to create magic login link.');
            redirect(admin_url('magic_login'));
        }

        $this->session->set_flashdata('magic_login_link', $created['url']);

        if ($sendEmail && !empty($created['contact']['email'])) {
            $sent = $this->send_magic_login_email(
                $created['contact'],
                $created['url'],
                $created['expires_at'],
                $redirectPath
            );

            if ($sent) {
                set_alert('success', 'Magic login link created and emailed to ' . $created['contact']['email'] . '.');
            } else {
                set_alert('warning', 'Magic login link created, but email sending failed. Copy the link manually.');
            }
        } else {
            set_alert('success', 'Magic login link created.');
        }

        redirect(admin_url('magic_login'));
    }

    public function revoke()
    {
        if (!staff_can('delete', 'magic_login') && !is_admin()) {
            access_denied('magic_login');
        }

        if (!$this->input->post()) {
            show_404();
        }

        $id = (int) $this->input->post('id');
        if ($this->magic_login_service->revoke_token($id, (int) get_staff_user_id())) {
            set_alert('success', 'Magic login link revoked.');
        } else {
            set_alert('warning', 'The link could not be revoked. It may already be used or revoked.');
        }

        redirect(admin_url('magic_login'));
    }

    public function save_settings()
    {
        if (!is_admin()) {
            access_denied('magic_login');
        }

        if (!$this->input->post()) {
            show_404();
        }

        $expiry = max(5, min(10080, (int) $this->input->post('default_expiry_minutes')));
        $otpExpiry = max(1, min(30, (int) $this->input->post('otp_expiry_minutes')));
        $otpAttempts = max(1, min(10, (int) $this->input->post('otp_max_attempts')));
        $whatsappUrl = trim((string) $this->input->post('whatsapp_api_url', true));
        $whatsappMessage = trim((string) $this->input->post('whatsapp_message', false));
        $updatePolicy = trim((string) $this->input->post('update_policy', true));

        if (!in_array($updatePolicy, ['off', 'patch', 'safe'], true)) {
            $updatePolicy = 'off';
        }

        if ($whatsappUrl !== '' && !preg_match('#^https://#i', $whatsappUrl)) {
            set_alert('warning', 'WhatsApp API URL must use HTTPS.');
            redirect(admin_url('magic_login'));
        }

        if ($whatsappMessage === '') {
            $whatsappMessage = 'Your {company} login code is {code}. It expires in {minutes} minutes.';
        }

        update_option('magic_login_default_expiry_minutes', $expiry);
        update_option('magic_login_auto_secure_email_links', $this->input->post('auto_secure_email_links') ? 1 : 0);
        update_option('magic_login_disable_password_login', $this->input->post('disable_password_login') ? 1 : 0);
        update_option('magic_login_altcha_enabled', $this->input->post('altcha_enabled') ? 1 : 0);
        update_option('magic_login_whatsapp_enabled', $this->input->post('whatsapp_enabled') ? 1 : 0);
        update_option('magic_login_whatsapp_api_url', $whatsappUrl);
        update_option('magic_login_whatsapp_message', $whatsappMessage);
        update_option('magic_login_otp_expiry_minutes', $otpExpiry);
        update_option('magic_login_otp_max_attempts', $otpAttempts);
        update_option('magic_login_api_enabled', $this->input->post('api_enabled') ? 1 : 0);
        update_option('magic_login_update_policy', $updatePolicy);

        $whatsappToken = trim((string) $this->input->post('whatsapp_api_token', false));
        if ($whatsappToken !== '') {
            update_option('magic_login_whatsapp_api_token', $whatsappToken);
        }
        if ($this->input->post('clear_whatsapp_api_token')) {
            update_option('magic_login_whatsapp_api_token', '');
        }

        set_alert('success', 'Magic Login settings saved.');
        redirect(admin_url('magic_login'));
    }

    public function generate_api_key()
    {
        if (!is_admin()) {
            access_denied('magic_login');
        }
        if (!$this->input->post()) {
            show_404();
        }

        try {
            $key = 'ml_' . bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            log_message('error', 'Magic Login API key generation failed: ' . $e->getMessage());
            set_alert('warning', 'Unable to generate an API key.');
            redirect(admin_url('magic_login'));
        }

        update_option('magic_login_api_key_hash', hash('sha256', $key));
        $this->session->set_flashdata('magic_login_new_api_key', $key);
        set_alert('success', 'New API key generated. Copy it now; it will not be shown again.');
        redirect(admin_url('magic_login'));
    }

    public function revoke_api_key()
    {
        if (!is_admin()) {
            access_denied('magic_login');
        }
        if (!$this->input->post()) {
            show_404();
        }

        update_option('magic_login_api_key_hash', '');
        update_option('magic_login_api_enabled', '0');
        set_alert('success', 'Magic Login API key revoked and API access disabled.');
        redirect(admin_url('magic_login'));
    }

    public function check_updates()
    {
        if (!is_admin()) {
            access_denied('magic_login');
        }
        if (!$this->input->post()) {
            show_404();
        }

        $this->load->library('magic_login/Magic_login_updater');
        $release = $this->magic_login_updater->latest_release(true);

        if ($release) {
            set_alert('success', 'Magic Login v' . $release['version'] . ' is available on GitHub.');
        } else {
            set_alert('info', 'Magic Login is up to date, or GitHub could not be reached.');
        }

        redirect(admin_url('magic_login'));
    }

    public function install_update()
    {
        if (!is_admin()) {
            access_denied('magic_login');
        }
        if (!$this->input->post()) {
            show_404();
        }

        $this->load->library('magic_login/Magic_login_updater');
        $result = $this->magic_login_updater->install_latest(false);

        set_alert(!empty($result['ok']) ? 'success' : 'danger', isset($result['message']) ? $result['message'] : 'Magic Login update failed.');
        redirect(admin_url('magic_login'));
    }

    private function endpoint_options()
    {
        return [
            'clients'          => 'Client Dashboard',
            'vault/client'     => 'Vault Portal',
            'clients/projects' => 'Projects',
            'clients/tickets'  => 'Support Tickets',
            'clients/profile'  => 'Profile',
            'custom'           => 'Custom Endpoint',
        ];
    }

    private function resolve_endpoint_path($selected, $custom)
    {
        $selected = trim((string) $selected);
        $custom = trim((string) $custom);

        if ($selected === 'custom') {
            $selected = $custom;
        }

        return $this->magic_login_service->normalize_destination($selected !== '' ? $selected : 'clients');
    }

    private function send_magic_login_email(array $contact, $link, $expiresAt, $redirectPath)
    {
        $fromEmail = trim((string) get_option('smtp_email'));
        if ($fromEmail === '') {
            $fromEmail = trim((string) get_option('companyemail'));
        }
        if ($fromEmail === '') {
            $host = parse_url(site_url(), PHP_URL_HOST);
            $fromEmail = 'no-reply@' . ($host ?: 'localhost');
        }

        $fromName = trim((string) get_option('companyname'));
        if ($fromName === '') {
            $fromName = 'Support';
        }

        $this->email->clear(true);
        $this->email->from($fromEmail, $fromName);
        $this->email->to((string) $contact['email']);
        $this->email->subject('Your secure magic login link');

        $endpointLabel = site_url($redirectPath !== '' ? $redirectPath : 'clients');
        $message = '<p>Hello ' . html_escape(trim((string) $contact['firstname'] . ' ' . (string) $contact['lastname'])) . ',</p>'
            . '<p>Your secure login link is ready.</p>'
            . '<p><a href="' . html_escape($link) . '">Click here to sign in</a></p>'
            . '<p>This link expires at <strong>' . html_escape(_dt($expiresAt)) . '</strong>.</p>'
            . '<p>Destination after login: ' . html_escape($endpointLabel) . '</p>';

        $this->email->message($message);
        return (bool) $this->email->send();
    }
}
