<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-7">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin">Create Magic Login Link</h4>
            <hr class="hr-panel-heading" />

            <?php $link = $this->session->flashdata('magic_login_link'); if ($link) { ?>
              <div class="alert alert-success">
                <strong>Link:</strong>
                <code style="word-break:break-all;"><?php echo html_escape($link); ?></code>
              </div>
            <?php } ?>

            <form method="post" action="<?php echo admin_url('magic_login/create'); ?>">
              <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">

              <div class="form-group">
                <label for="contact_id">Contact</label>
                <select class="form-control" name="contact_id" id="contact_id" required>
                  <option value="">Select contact</option>
                  <?php foreach ($contacts as $c) { ?>
                    <option value="<?php echo (int) $c['id']; ?>">
                      <?php echo html_escape($c['company'] . ' - ' . $c['firstname'] . ' ' . $c['lastname'] . ' (' . $c['email'] . ')'); ?>
                    </option>
                  <?php } ?>
                </select>
              </div>

              <?php $defaultHours = max(1, (int) ceil(($settings['default_expiry_minutes'] ?? 60) / 60)); ?>
              <?php echo render_input('hours', 'Validity (hours)', (string) $defaultHours, 'number', ['min' => 1, 'max' => 168]); ?>

              <div class="form-group">
                <label for="portal_endpoint">Client Portal Destination</label>
                <select class="form-control" name="portal_endpoint" id="portal_endpoint">
                  <?php foreach (($endpoint_options ?? []) as $endpointValue => $endpointLabel) { ?>
                    <option value="<?php echo html_escape($endpointValue); ?>" <?php echo $endpointValue === 'clients' ? 'selected' : ''; ?>>
                      <?php echo html_escape($endpointLabel); ?>
                    </option>
                  <?php } ?>
                </select>
              </div>

              <div class="form-group" id="custom-endpoint-wrap" style="display:none;">
                <label for="custom_endpoint">Custom Endpoint</label>
                <input type="text" class="form-control" name="custom_endpoint" id="custom_endpoint" placeholder="clients/invoices">
                <p class="text-muted mtop5">Same-site relative paths only.</p>
              </div>

              <div class="checkbox checkbox-primary">
                <input type="checkbox" id="send_email" name="send_email" value="1" checked>
                <label for="send_email">Send link to contact email immediately</label>
              </div>

              <button class="btn btn-primary" type="submit">Generate Link</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-md-5">
        <?php if (is_admin()) { ?>
          <form method="post" action="<?php echo admin_url('magic_login/save_settings'); ?>">
            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">

            <div class="panel_s">
              <div class="panel-body">
                <h4 class="no-margin">General & Email</h4>
                <hr class="hr-panel-heading" />

                <?php echo render_input(
                    'default_expiry_minutes',
                    'Default token validity (minutes)',
                    (string) ($settings['default_expiry_minutes'] ?? 60),
                    'number',
                    ['min' => 5, 'max' => 10080]
                ); ?>

                <div class="checkbox checkbox-primary">
                  <input type="checkbox" id="auto_secure_email_links" name="auto_secure_email_links" value="1" <?php echo !empty($settings['auto_secure_email_links']) ? 'checked' : ''; ?>>
                  <label for="auto_secure_email_links">Automatically secure customer links in supported email templates</label>
                </div>
                <p class="text-muted">
                  Supported invoice, estimate, proposal, contract, ticket and project links become one-time login links for the actual email recipient.
                </p>
              </div>
            </div>

            <div class="panel_s">
              <div class="panel-body">
                <h4 class="no-margin">WhatsApp Login</h4>
                <hr class="hr-panel-heading" />

                <div class="checkbox checkbox-primary">
                  <input type="checkbox" id="whatsapp_enabled" name="whatsapp_enabled" value="1" <?php echo !empty($settings['whatsapp_enabled']) ? 'checked' : ''; ?>>
                  <label for="whatsapp_enabled">Enable “Continue with WhatsApp” on the client login page</label>
                </div>

                <?php echo render_input(
                    'whatsapp_api_url',
                    'Baileys / WhatsApp API URL',
                    (string) ($settings['whatsapp_api_url'] ?? ''),
                    'url',
                    ['placeholder' => 'https://whatsapp.example.com/api/send']
                ); ?>

                <div class="form-group">
                  <label for="whatsapp_api_token">WhatsApp API Bearer Token</label>
                  <input type="password" class="form-control" name="whatsapp_api_token" id="whatsapp_api_token" autocomplete="new-password" placeholder="<?php echo !empty($settings['whatsapp_token_set']) ? 'Configured — leave blank to keep current token' : 'Optional'; ?>">
                </div>

                <?php if (!empty($settings['whatsapp_token_set'])) { ?>
                  <div class="checkbox checkbox-danger">
                    <input type="checkbox" id="clear_whatsapp_api_token" name="clear_whatsapp_api_token" value="1">
                    <label for="clear_whatsapp_api_token">Clear the saved WhatsApp API token</label>
                  </div>
                <?php } ?>

                <div class="form-group">
                  <label for="whatsapp_message">OTP Message</label>
                  <textarea class="form-control" rows="3" name="whatsapp_message" id="whatsapp_message"><?php echo html_escape((string) ($settings['whatsapp_message'] ?? '')); ?></textarea>
                  <p class="text-muted mtop5">Available: <code>{company}</code> <code>{code}</code> <code>{minutes}</code></p>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <?php echo render_input('otp_expiry_minutes', 'OTP validity (minutes)', (string) ($settings['otp_expiry_minutes'] ?? 5), 'number', ['min' => 1, 'max' => 30]); ?>
                  </div>
                  <div class="col-md-6">
                    <?php echo render_input('otp_max_attempts', 'Max OTP attempts', (string) ($settings['otp_max_attempts'] ?? 5), 'number', ['min' => 1, 'max' => 10]); ?>
                  </div>
                </div>

                <p class="text-muted">
                  Default outbound JSON is <code>{"to":"+234...","message":"..."}</code>. Perfex hooks can adapt the payload and headers for a different Baileys endpoint.
                </p>
              </div>
            </div>

            <div class="panel_s">
              <div class="panel-body">
                <h4 class="no-margin">API Access</h4>
                <hr class="hr-panel-heading" />

                <div class="checkbox checkbox-primary">
                  <input type="checkbox" id="api_enabled" name="api_enabled" value="1" <?php echo !empty($settings['api_enabled']) ? 'checked' : ''; ?> <?php echo empty($settings['api_key_set']) ? 'disabled' : ''; ?>>
                  <label for="api_enabled">Enable external Magic Login API</label>
                </div>

                <p class="text-muted">
                  Authentication: <code>Authorization: Bearer YOUR_KEY</code>. API keys are stored only as SHA-256 hashes.
                </p>
                <p class="text-muted mbot0">
                  Endpoints: <code>/magic_login/api/create-link</code>, <code>/request-otp</code>, <code>/verify-otp</code>, <code>/revoke</code>.
                </p>
              </div>
            </div>

            <button class="btn btn-primary btn-block mbot15" type="submit">Save Magic Login Settings</button>
          </form>

          <div class="panel_s">
            <div class="panel-body">
              <h4 class="no-margin">API Key</h4>
              <hr class="hr-panel-heading" />

              <?php if (!empty($new_api_key)) { ?>
                <div class="alert alert-warning">
                  <strong>Copy this key now. It will not be shown again.</strong><br>
                  <code style="word-break:break-all;"><?php echo html_escape($new_api_key); ?></code>
                </div>
              <?php } ?>

              <p><?php echo !empty($settings['api_key_set']) ? '<span class="label label-success">API key configured</span>' : '<span class="label label-default">No API key</span>'; ?></p>

              <form method="post" action="<?php echo admin_url('magic_login/generate_api_key'); ?>" class="mbot10">
                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                <button type="submit" class="btn btn-default btn-block"><?php echo !empty($settings['api_key_set']) ? 'Rotate API Key' : 'Generate API Key'; ?></button>
              </form>

              <?php if (!empty($settings['api_key_set'])) { ?>
                <form method="post" action="<?php echo admin_url('magic_login/revoke_api_key'); ?>">
                  <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                  <button type="submit" class="btn btn-danger btn-block">Revoke API Key</button>
                </form>
              <?php } ?>
            </div>
          </div>
        <?php } else { ?>
          <div class="panel_s"><div class="panel-body"><p class="text-muted">Only administrators can change Magic Login settings.</p></div></div>
        <?php } ?>
      </div>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <h4 class="no-margin">Recent Tokens</h4>
            <hr class="hr-panel-heading" />
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>Contact</th><th>Company</th><th>Source</th><th>Context</th><th>Destination</th><th>Expires</th><th>Status</th><th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($tokens as $t) {
                      if (!empty($t['revoked_at'])) { $status = 'Revoked'; $statusClass = 'label-danger'; }
                      elseif (!empty($t['used_at'])) { $status = 'Used'; $statusClass = 'label-success'; }
                      elseif (strtotime($t['expires_at']) <= time()) { $status = 'Expired'; $statusClass = 'label-default'; }
                      else { $status = 'Active'; $statusClass = 'label-info'; }
                  ?>
                    <tr>
                      <td><?php echo html_escape($t['firstname'] . ' ' . $t['lastname'] . ' (' . $t['email'] . ')'); ?></td>
                      <td><?php echo html_escape((string) $t['company']); ?></td>
                      <td><?php echo html_escape(isset($t['source']) ? $t['source'] : 'manual'); ?></td>
                      <td><?php $context = !empty($t['context_type']) ? $t['context_type'] : 'portal'; if (!empty($t['context_id'])) { $context .= ' #' . (int) $t['context_id']; } echo html_escape($context); ?></td>
                      <td style="max-width:260px;word-break:break-all;"><?php echo html_escape(site_url(!empty($t['redirect_path']) ? $t['redirect_path'] : 'clients')); ?></td>
                      <td><?php echo html_escape(_dt($t['expires_at'])); ?></td>
                      <td><span class="label <?php echo $statusClass; ?>"><?php echo $status; ?></span></td>
                      <td>
                        <?php if ($status === 'Active' && (staff_can('delete', 'magic_login') || is_admin())) { ?>
                          <form method="post" action="<?php echo admin_url('magic_login/revoke'); ?>" style="display:inline;">
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                            <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                            <button class="btn btn-danger btn-xs" type="submit">Revoke</button>
                          </form>
                        <?php } ?>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php if (!empty($audit)) { ?>
      <div class="row">
        <div class="col-md-12">
          <div class="panel_s">
            <div class="panel-body">
              <h4 class="no-margin">Recent Audit Activity</h4>
              <hr class="hr-panel-heading" />
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead><tr><th>Time</th><th>Event</th><th>Token</th><th>Contact</th><th>IP</th></tr></thead>
                  <tbody>
                    <?php foreach ($audit as $entry) { ?>
                      <tr>
                        <td><?php echo html_escape(_dt($entry['created_at'])); ?></td>
                        <td><?php echo html_escape($entry['event']); ?></td>
                        <td><?php echo $entry['token_id'] !== null ? '#' . (int) $entry['token_id'] : '-'; ?></td>
                        <td><?php echo $entry['contact_id'] !== null ? '#' . (int) $entry['contact_id'] : '-'; ?></td>
                        <td><?php echo html_escape((string) $entry['ip_address']); ?></td>
                      </tr>
                    <?php } ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php } ?>
  </div>
</div>
<?php init_tail(); ?>
<script>
(function () {
  var endpoint = document.getElementById('portal_endpoint');
  var customWrap = document.getElementById('custom-endpoint-wrap');
  if (endpoint && customWrap) {
    function syncCustom() { customWrap.style.display = endpoint.value === 'custom' ? '' : 'none'; }
    endpoint.addEventListener('change', syncCustom);
    syncCustom();
  }
})();
</script>
