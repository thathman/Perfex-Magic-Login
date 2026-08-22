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

        $data['endpoint_options'] = $this->endpoint_options();
        $data['stats'] = $this->operational_stats();
        $data['module_version'] = defined('MAGIC_LOGIN_VERSION') ? MAGIC_LOGIN_VERSION : 'unknown';
        $data['active_tab'] = 'links';

        $data['title'] = 'Magic Login';
        $this->load->view('magic_login/manage', $data);
    }

    public function audit()
    {
        if (!staff_can('view', 'magic_login') && !is_admin()) {
            access_denied('magic_login');
        }

        $data['title'] = 'Magic Login — Audit Log';
        $data['module_version'] = defined('MAGIC_LOGIN_VERSION') ? MAGIC_LOGIN_VERSION : 'unknown';
        $data['active_tab'] = 'audit';
        $this->load->view('magic_login/audit', $data);
    }

    public function contacts()
    {
        if ((!staff_can('create', 'magic_login') && !is_admin()) || !$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $term = trim((string) $this->input->get('q', true));
        $this->db->select('c.id,c.firstname,c.lastname,c.email,cl.company')
            ->from(db_prefix() . 'contacts c')
            ->join(db_prefix() . 'clients cl', 'cl.userid=c.userid', 'left')
            ->where('c.active', 1)
            ->limit(20);

        if ($term !== '') {
            $this->db->group_start()
                ->like('c.firstname', $term)
                ->or_like('c.lastname', $term)
                ->or_like('c.email', $term)
                ->or_like('cl.company', $term)
                ->group_end();
        }

        $results = [];
        foreach ($this->db->order_by('c.id', 'DESC')->get()->result_array() as $contact) {
            $name = trim($contact['firstname'] . ' ' . $contact['lastname']);
            $company = trim((string) $contact['company']);
            $label = trim(($company !== '' ? $company . ' — ' : '') . $name);
            $label .= $contact['email'] !== '' ? ' — ' . $contact['email'] : '';
            $results[] = [
                'id'   => (int) $contact['id'],
                'text' => $label,
            ];
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(['results' => $results]));
    }

    public function table()
    {
        if ((!staff_can('view', 'magic_login') && !is_admin()) || !$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $aColumns = [
            'CONCAT_WS(CHAR(32), c.firstname, c.lastname, c.email, cl.company) as contact_search',
            't.source as source',
            't.context_type as context_type',
            't.redirect_path as redirect_path',
            't.created_at as created_at',
            't.expires_at as expires_at',
            't.used_at as used_at',
            't.revoked_at as revoked_at',
        ];
        $join = [
            'LEFT JOIN ' . db_prefix() . 'contacts c ON c.id = t.contact_id',
            'LEFT JOIN ' . db_prefix() . 'clients cl ON cl.userid = c.userid',
        ];
        $where = $this->token_table_filters();
        $result = data_tables_init($aColumns, 't.id', db_prefix() . 'magic_login_tokens t', $join, $where, [
            't.id as id',
            'c.firstname as firstname',
            'c.lastname as lastname',
            'c.email as email',
            'cl.company as company',
            't.context_id as context_id',
        ]);
        $output = $result['output'];

        foreach ($result['rResult'] as $row) {
            $status = $this->token_status($row);
            $contact = trim($row['firstname'] . ' ' . $row['lastname']);
            $contactLabel = $contact !== '' ? e($contact) : 'Unknown contact';
            if ($row['email'] !== '') {
                $contactLabel .= '<span class="text-muted small block">' . e($row['email']) . '</span>';
            }

            $context = $row['context_type'] !== '' && $row['context_type'] !== null ? $row['context_type'] : 'portal';
            if (!empty($row['context_id'])) {
                $context .= ' #' . (int) $row['context_id'];
            }

            $destination = '<span class="text-muted" title="' . e($this->magic_login_service->destination_url($row)) . '">' . e($row['redirect_path'] ?: 'clients') . '</span>';
            $rowData = [
                $contactLabel,
                e($row['source'] ?: 'manual'),
                e($context),
                $destination,
                e(_dt($row['created_at'])),
                e(_dt($row['expires_at'])),
                '<span class="label ' . e($status['class']) . '">' . e($status['label']) . '</span>',
            ];

            $actions = '<div class="tw-flex tw-items-center tw-space-x-2">';
            if ($status['label'] === 'Active' && (staff_can('delete', 'magic_login') || is_admin())) {
                $actions .= '<form method="post" action="' . admin_url('magic_login/revoke') . '" class="tw-inline-block">'
                    . '<input type="hidden" name="' . $this->security->get_csrf_token_name() . '" value="' . $this->security->get_csrf_hash() . '">'
                    . '<input type="hidden" name="id" value="' . (int) $row['id'] . '">'
                    . '<button class="btn btn-link text-danger p0" type="submit" title="Revoke link"><i class="fa-regular fa-circle-xmark"></i> <span class="sr-only">Revoke</span></button>'
                    . '</form>';
            }
            $actions .= '</div>';
            $rowData[] = $actions;
            $output['aaData'][] = $rowData;
        }

        $this->output->set_content_type('application/json')->set_output(json_encode($output));
    }

    public function audit_table()
    {
        if ((!staff_can('view', 'magic_login') && !is_admin()) || !$this->input->is_ajax_request()) {
            ajax_access_denied();
        }

        $auditTable = db_prefix() . 'magic_login_audit';
        if (!$this->db->table_exists($auditTable)) {
            $this->output->set_content_type('application/json')->set_output(json_encode([
                'draw'            => (int) $this->input->post('draw'),
                'iTotalRecords'   => 0,
                'iTotalDisplayRecords' => 0,
                'aaData'          => [],
            ]));
            return;
        }

        $aColumns = [
            'a.created_at as created_at',
            'a.event as event',
            'CONCAT_WS(CHAR(32), c.firstname, c.lastname, c.email) as contact_search',
            'a.token_id as token_id',
            'a.ip_address as ip_address',
            'a.metadata as metadata',
        ];
        $join = ['LEFT JOIN ' . db_prefix() . 'contacts c ON c.id = a.contact_id'];
        $where = $this->audit_table_filters();
        $result = data_tables_init($aColumns, 'a.id', $auditTable . ' a', $join, $where, [
            'a.id as id',
            'c.firstname as firstname',
            'c.lastname as lastname',
            'c.email as email',
        ]);
        $output = $result['output'];

        foreach ($result['rResult'] as $row) {
            $contact = e(trim($row['firstname'] . ' ' . $row['lastname']));
            if ($row['email'] !== '') {
                $contact .= ($contact !== '' ? ' ' : '') . '<span class="text-muted small block">' . e($row['email']) . '</span>';
            }
            $details = '-';
            if (!empty($row['metadata'])) {
                $decoded = json_decode($row['metadata'], true);
                if (is_array($decoded)) {
                    $details = e(implode(' · ', array_map(function ($key, $value) {
                        return $key . ': ' . (is_scalar($value) ? $value : json_encode($value));
                    }, array_keys($decoded), array_values($decoded))));
                }
            }

            $output['aaData'][] = [
                e(_dt($row['created_at'])),
                '<span class="label label-default">' . e(str_replace('_', ' ', $row['event'])) . '</span>',
                $contact !== '' ? $contact : '-',
                $row['token_id'] !== null ? '#' . (int) $row['token_id'] : '-',
                e((string) $row['ip_address']),
                '<span class="text-muted small">' . $details . '</span>',
            ];
        }

        $this->output->set_content_type('application/json')->set_output(json_encode($output));
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

        $nativeSettings = $this->input->post('settings_context', true) === 'native';
        $redirect = $nativeSettings ? admin_url('settings?group=magic_login') : admin_url('magic_login');

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
            redirect($redirect);
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
        $apiKeySet = trim((string) get_option('magic_login_api_key_hash')) !== '';
        update_option('magic_login_api_enabled', $this->input->post('api_enabled') && $apiKeySet ? 1 : 0);
        update_option('magic_login_update_policy', $updatePolicy);

        $whatsappToken = trim((string) $this->input->post('whatsapp_api_token', false));
        if ($whatsappToken !== '') {
            update_option('magic_login_whatsapp_api_token', $whatsappToken);
        }
        if ($this->input->post('clear_whatsapp_api_token')) {
            update_option('magic_login_whatsapp_api_token', '');
        }

        $action = trim((string) $this->input->post('settings_action', true));
        if ($action === 'generate_api_key') {
            try {
                $key = 'ml_' . bin2hex(random_bytes(32));
                update_option('magic_login_api_key_hash', hash('sha256', $key));
                $this->session->set_flashdata('magic_login_new_api_key', $key);
                set_alert('success', 'New API key generated. Copy it now; it will not be shown again.');
            } catch (Throwable $e) {
                log_message('error', 'Magic Login API key generation failed: ' . $e->getMessage());
                set_alert('warning', 'Unable to generate an API key.');
            }
        } elseif ($action === 'revoke_api_key') {
            update_option('magic_login_api_key_hash', '');
            update_option('magic_login_api_enabled', '0');
            set_alert('success', 'Magic Login API key revoked and API access disabled.');
        } elseif ($action === 'check_updates' || $action === 'install_update') {
            $this->load->library('magic_login/Magic_login_updater');
            if ($action === 'check_updates') {
                $release = $this->magic_login_updater->latest_release(true);
                set_alert($release ? 'success' : 'info', $release
                    ? 'Magic Login v' . $release['version'] . ' is available on GitHub.'
                    : 'Magic Login is up to date, or GitHub could not be reached.');
            } else {
                $result = $this->magic_login_updater->install_latest(false);
                set_alert(!empty($result['ok']) ? 'success' : 'danger', isset($result['message']) ? $result['message'] : 'Magic Login update failed.');
            }
        } else {
            set_alert('success', 'Magic Login settings saved.');
        }

        redirect($redirect);
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

    private function operational_stats()
    {
        $tokensTable = db_prefix() . 'magic_login_tokens';
        $auditTable = db_prefix() . 'magic_login_audit';
        $stats = [
            'active'             => 0,
            'used_today'         => 0,
            'expired'            => 0,
            'failed_otp_today'   => 0,
        ];

        if (!$this->db->table_exists($tokensTable)) {
            return $stats;
        }

        $this->db->where('used_at IS NULL', null, false)
            ->where('revoked_at IS NULL', null, false)
            ->where('expires_at >', date('Y-m-d H:i:s'));
        $stats['active'] = (int) $this->db->count_all_results($tokensTable);

        $today = date('Y-m-d 00:00:00');
        $this->db->where('used_at >=', $today);
        $stats['used_today'] = (int) $this->db->count_all_results($tokensTable);

        $this->db->where('used_at IS NULL', null, false)
            ->where('revoked_at IS NULL', null, false)
            ->where('expires_at <=', date('Y-m-d H:i:s'));
        $stats['expired'] = (int) $this->db->count_all_results($tokensTable);

        if ($this->db->table_exists($auditTable)) {
            $this->db->where('event', 'otp_failed')->where('created_at >=', $today);
            $stats['failed_otp_today'] = (int) $this->db->count_all_results($auditTable);
        }

        return $stats;
    }

    private function token_status(array $row)
    {
        if (!empty($row['revoked_at'])) {
            return ['label' => 'Revoked', 'class' => 'label-danger'];
        }
        if (!empty($row['used_at'])) {
            return ['label' => 'Used', 'class' => 'label-success'];
        }
        if (strtotime($row['expires_at']) <= time()) {
            return ['label' => 'Expired', 'class' => 'label-default'];
        }

        return ['label' => 'Active', 'class' => 'label-info'];
    }

    private function token_table_filters()
    {
        $where = [];
        $status = trim((string) $this->input->post('status', true));
        $source = trim((string) $this->input->post('source', true));

        if ($status === 'active') {
            $where[] = 'AND t.used_at IS NULL AND t.revoked_at IS NULL AND t.expires_at > NOW()';
        } elseif ($status === 'used') {
            $where[] = 'AND t.used_at IS NOT NULL';
        } elseif ($status === 'expired') {
            $where[] = 'AND t.used_at IS NULL AND t.revoked_at IS NULL AND t.expires_at <= NOW()';
        } elseif ($status === 'revoked') {
            $where[] = 'AND t.revoked_at IS NOT NULL';
        }

        if (in_array($source, ['manual', 'email', 'api', 'whatsapp'], true)) {
            $where[] = 'AND t.source = ' . $this->db->escape($source);
        }

        return $where;
    }

    private function audit_table_filters()
    {
        $where = [];
        $event = trim((string) $this->input->post('event', true));
        $from = trim((string) $this->input->post('date_from', true));
        $to = trim((string) $this->input->post('date_to', true));

        if ($event !== '' && preg_match('/^[a-z0-9_-]{1,50}$/i', $event)) {
            $where[] = 'AND a.event = ' . $this->db->escape(strtolower($event));
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[] = 'AND a.created_at >= ' . $this->db->escape($from . ' 00:00:00');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[] = 'AND a.created_at <= ' . $this->db->escape($to . ' 23:59:59');
        }

        return $where;
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
