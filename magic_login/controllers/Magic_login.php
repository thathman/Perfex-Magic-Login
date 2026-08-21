<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Magic_login extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->ensure_schema();
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
        $data['endpoint_options'] = $this->endpoint_options();

        $data['title'] = 'Magic Login';
        $this->load->view('magic_login/manage', $data);
    }

    public function create()
    {
        if (!staff_can('create', 'magic_login') && !is_admin()) {
            access_denied('magic_login');
        }

        $contactId = (int)$this->input->post('contact_id');
        $hours = max(1, min(72, (int)$this->input->post('hours')));
        $redirectPath = $this->resolve_endpoint_path(
            (string)$this->input->post('portal_endpoint', true),
            (string)$this->input->post('custom_endpoint', true)
        );
        $sendEmail = (int)$this->input->post('send_email') === 1;

        $contact = $this->db->where('id', $contactId)->where('active',1)->get(db_prefix() . 'contacts')->row_array();
        if (!$contact) {
            set_alert('warning', 'Contact not found or inactive.');
            redirect(admin_url('magic_login'));
        }
        if (empty($contact['userid'])) {
            set_alert('warning', 'Selected contact is not linked to a valid client account.');
            redirect(admin_url('magic_login'));
        }

        try {
            $token = bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            log_message('error', 'Magic Login token generation failed: ' . $e->getMessage());
            set_alert('warning', 'Failed to generate secure login token.');
            redirect(admin_url('magic_login'));
        }

        $this->db->insert(db_prefix() . 'magic_login_tokens', [
            'contact_id'     => $contactId,
            'token_hash'     => hash('sha256', $token),
            'expires_at'     => date('Y-m-d H:i:s', strtotime('+' . $hours . ' hours')),
            'used_at'        => null,
            'created_by'     => (int)get_staff_user_id(),
            'created_at'     => date('Y-m-d H:i:s'),
            'redirect_path'  => $redirectPath !== '' ? $redirectPath : null,
        ]);

        if ($this->db->affected_rows() < 1) {
            $dbError = $this->db->error();
            log_message('error', 'Magic Login create failed. DB code: ' . ($dbError['code'] ?? 'n/a') . ' message: ' . ($dbError['message'] ?? 'n/a'));
            set_alert('warning', 'Magic login creation failed (code ' . (int)($dbError['code'] ?? 0) . ').');
            redirect(admin_url('magic_login'));
        }

        $link = $this->build_magic_link($token, $redirectPath);
        $this->session->set_flashdata('magic_login_link', $link);

        if ($sendEmail && !empty($contact['email'])) {
            $sent = $this->send_magic_login_email($contact, $link, $hours, $redirectPath);
            if ($sent) {
                set_alert('success', 'Magic login link created and emailed to ' . $contact['email'] . '.');
            } else {
                set_alert('warning', 'Magic login created, but email sending failed. Copy the link manually.');
            }
        } else {
            set_alert('success', 'Magic login link created.');
        }
        redirect(admin_url('magic_login'));
    }

    public function revoke($id)
    {
        if (!staff_can('delete', 'magic_login') && !is_admin()) {
            access_denied('magic_login');
        }
        $this->db->where('id', (int)$id)->update(db_prefix() . 'magic_login_tokens', ['used_at' => date('Y-m-d H:i:s')]);
        set_alert('success', 'Magic link revoked.');
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
        $selected = trim((string)$selected);
        $custom = trim((string)$custom);

        if ($selected === 'custom') {
            $selected = $custom;
        }

        if ($selected === '') {
            return '';
        }

        // Accept only same-site relative paths.
        if (preg_match('#^https?://#i', $selected)) {
            $base = rtrim(site_url(), '/');
            if (stripos($selected, $base) !== 0) {
                return '';
            }
            $selected = ltrim(substr($selected, strlen($base)), '/');
        }

        $selected = ltrim($selected, '/');
        return preg_replace('#[^a-zA-Z0-9/_\\-]#', '', $selected);
    }

    private function build_magic_link($token, $redirectPath)
    {
        // Use query-string endpoint for maximum routing compatibility on this stack.
        $link = site_url('magic_login/magiclink?token=' . rawurlencode($token));
        if ($redirectPath !== '') {
            $link .= '&next=' . rawurlencode(site_url($redirectPath));
        }
        return $link;
    }

    private function send_magic_login_email(array $contact, $link, $hours, $redirectPath)
    {
        $fromEmail = trim((string)get_option('smtp_email'));
        if ($fromEmail === '') {
            $fromEmail = trim((string)get_option('companyemail'));
        }
        if ($fromEmail === '') {
            $host = parse_url(site_url(), PHP_URL_HOST);
            $fromEmail = 'no-reply@' . ($host ?: 'localhost');
        }
        $fromName = trim((string)get_option('companyname'));
        if ($fromName === '') {
            $fromName = 'Support';
        }

        $this->email->clear(true);
        $this->email->from($fromEmail, $fromName);
        $this->email->to((string)$contact['email']);
        $this->email->subject('Your secure magic login link');
        $endpointLabel = $redirectPath !== '' ? site_url($redirectPath) : site_url('clients');
        $message = '<p>Hello ' . html_escape(trim((string)$contact['firstname'] . ' ' . (string)$contact['lastname'])) . ',</p>'
            . '<p>Your secure login link is ready.</p>'
            . '<p><a href="' . html_escape($link) . '">Click here to sign in</a></p>'
            . '<p>This link expires in <strong>' . (int)$hours . ' hour(s)</strong>.</p>'
            . '<p>Destination after login: ' . html_escape($endpointLabel) . '</p>';
        $this->email->message($message);
        return (bool)$this->email->send();
    }

    private function ensure_schema()
    {
        $table = db_prefix() . 'magic_login_tokens';
        if (!$this->db->field_exists('redirect_path', $table)) {
            $this->db->query('ALTER TABLE `' . $table . '` ADD `redirect_path` VARCHAR(191) NULL AFTER `created_at`');
        }
    }
}


