<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$CI = &get_instance();
$tokenConfigured = trim((string) get_option('magic_login_whatsapp_api_token')) !== '';
$apiConfigured = trim((string) get_option('magic_login_api_key_hash')) !== '';
$updates = [];
$updatesTable = db_prefix() . 'magic_login_updates';
if ($CI->db->table_exists($updatesTable)) {
    $updates = $CI->db->order_by('id', 'DESC')->limit(8)->get($updatesTable)->result_array();
}
?>
<?php if (!is_admin()) { ?>
    <div class="alert alert-warning">Magic Login configuration is available to administrators only.</div>
<?php return; } ?>

<div class="tw-flex tw-items-start tw-justify-between tw-gap-4 tw-mb-5">
    <div>
        <p class="text-muted tw-mb-1">Magic Login</p>
        <h3 class="tw-font-semibold tw-mt-0">Passwordless access settings</h3>
        <p class="text-muted tw-max-w-2xl tw-mb-0">Configure delivery and security policies here. Daily link operations stay in the Magic Login module.</p>
    </div>
    <a class="btn btn-default" href="<?php echo admin_url('magic_login'); ?>"><i class="fa-solid fa-arrow-up-right-from-square tw-mr-1"></i> Open operations</a>
</div>

<input type="hidden" name="settings_context" value="native">
<ul class="nav nav-tabs nav-tabs-segmented tw-mb-4" role="tablist">
    <li class="active"><a href="#magic-general" role="tab" data-toggle="tab">General</a></li>
    <li><a href="#magic-email" role="tab" data-toggle="tab">Email</a></li>
    <li><a href="#magic-whatsapp" role="tab" data-toggle="tab">WhatsApp</a></li>
    <li><a href="#magic-api" role="tab" data-toggle="tab">API</a></li>
    <li><a href="#magic-security" role="tab" data-toggle="tab">Security</a></li>
    <li><a href="#magic-updates" role="tab" data-toggle="tab">Updates</a></li>
</ul>

<div class="tab-content">
    <div role="tabpanel" class="tab-pane active" id="magic-general">
        <div class="row">
            <div class="col-md-8">
                <?php echo render_input('default_expiry_minutes', 'Default link validity (minutes)', (string) max(5, (int) get_option('magic_login_default_expiry_minutes')), 'number', ['min' => 5, 'max' => 10080]); ?>
                <p class="text-muted">Manual and automatic email links use this default when no shorter validity is supplied.</p>
            </div>
            <div class="col-md-4">
                <div class="panel_s tw-mt-0">
                    <div class="panel-body">
                        <p class="text-muted small tw-mb-1">Operational home</p>
                        <p class="tw-font-semibold tw-mb-2">Magic Login → Login Links</p>
                        <a href="<?php echo admin_url('magic_login'); ?>" class="btn btn-default btn-sm">View active links</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div role="tabpanel" class="tab-pane" id="magic-email">
        <div class="checkbox checkbox-primary">
            <input type="checkbox" id="auto_secure_email_links" name="auto_secure_email_links" value="1" <?php echo get_option('magic_login_auto_secure_email_links') == '1' ? 'checked' : ''; ?>>
            <label for="auto_secure_email_links">Automatically secure supported customer links in email templates</label>
        </div>
        <p class="text-muted">Invoice, estimate, proposal, contract, ticket and project links are converted to one-time access links for the actual recipient.</p>
        <hr>
        <h4 class="tw-font-semibold tw-mt-0">Available merge fields</h4>
        <div class="row">
            <?php foreach (['{magic_login_url}' => 'The one-time sign-in URL.', '{magic_login_button}' => 'A ready-to-use HTML sign-in button.', '{magic_login_expiry}' => 'The link expiration timestamp.'] as $tag => $description) { ?>
                <div class="col-md-4"><div class="well well-sm tw-h-full"><code><?php echo $tag; ?></code><p class="text-muted small tw-mb-0 tw-mt-2"><?php echo $description; ?></p></div></div>
            <?php } ?>
        </div>
    </div>

    <div role="tabpanel" class="tab-pane" id="magic-whatsapp">
        <div class="row">
            <div class="col-md-8">
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" id="whatsapp_enabled" name="whatsapp_enabled" value="1" <?php echo get_option('magic_login_whatsapp_enabled') == '1' ? 'checked' : ''; ?>>
                    <label for="whatsapp_enabled">Enable WhatsApp OTP login for client contacts</label>
                </div>
                <?php echo render_input('whatsapp_api_url', 'WhatsApp transport endpoint', (string) get_option('magic_login_whatsapp_api_url'), 'url', ['placeholder' => 'https://transport.example.com/send']); ?>
                <div class="form-group">
                    <label for="whatsapp_api_token">Bearer token</label>
                    <input type="password" class="form-control" name="whatsapp_api_token" id="whatsapp_api_token" autocomplete="new-password" placeholder="<?php echo $tokenConfigured ? 'Configured — leave blank to keep it' : 'Not configured'; ?>">
                </div>
                <?php if ($tokenConfigured) { ?>
                    <div class="checkbox checkbox-danger"><input type="checkbox" id="clear_whatsapp_api_token" name="clear_whatsapp_api_token" value="1"><label for="clear_whatsapp_api_token">Clear the saved transport token</label></div>
                <?php } ?>
                <div class="form-group">
                    <label for="whatsapp_message">OTP message</label>
                    <textarea class="form-control" rows="3" name="whatsapp_message" id="whatsapp_message"><?php echo html_escape((string) get_option('magic_login_whatsapp_message')); ?></textarea>
                    <p class="text-muted mtop5">Available tags: <code>{company}</code> <code>{code}</code> <code>{minutes}</code></p>
                </div>
                <div class="row">
                    <div class="col-md-6"><?php echo render_input('otp_expiry_minutes', 'OTP validity (minutes)', (string) max(1, (int) get_option('magic_login_otp_expiry_minutes')), 'number', ['min' => 1, 'max' => 30]); ?></div>
                    <div class="col-md-6"><?php echo render_input('otp_max_attempts', 'Maximum attempts', (string) max(1, (int) get_option('magic_login_otp_max_attempts')), 'number', ['min' => 1, 'max' => 10]); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="panel_s tw-mt-0"><div class="panel-body">
                    <h4 class="tw-font-semibold tw-mt-0">Transport status</h4>
                    <p><span class="label <?php echo get_option('magic_login_whatsapp_enabled') == '1' ? 'label-success' : 'label-default'; ?>"><?php echo get_option('magic_login_whatsapp_enabled') == '1' ? 'Enabled' : 'Disabled'; ?></span></p>
                    <dl class="small tw-mb-0">
                        <dt>Endpoint</dt><dd><?php echo get_option('magic_login_whatsapp_api_url') !== '' ? 'Configured' : 'Missing'; ?></dd>
                        <dt>Authentication</dt><dd><?php echo $tokenConfigured ? 'Configured' : 'Missing'; ?></dd>
                        <dt>Transport</dt><dd>HTTPS required</dd>
                    </dl>
                </div></div>
            </div>
        </div>
    </div>

    <div role="tabpanel" class="tab-pane" id="magic-api">
        <div class="row">
            <div class="col-md-8">
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" id="api_enabled" name="api_enabled" value="1" <?php echo get_option('magic_login_api_enabled') == '1' ? 'checked' : ''; ?> <?php echo !$apiConfigured ? 'disabled' : ''; ?>>
                    <label for="api_enabled">Enable the external Magic Login API</label>
                </div>
                <p class="text-muted">Use the Bearer-authenticated endpoints documented in the module API guide. Existing plaintext keys are never displayed.</p>
                <p class="small"><code>/magic_login/api/create-link</code> · <code>/request-otp</code> · <code>/verify-otp</code> · <code>/revoke</code></p>
            </div>
            <div class="col-md-4">
                <div class="panel_s tw-mt-0"><div class="panel-body">
                    <p class="text-muted small tw-mb-1">Key status</p>
                    <p class="tw-font-semibold"><?php echo $apiConfigured ? 'Configured' : 'Not configured'; ?></p>
                    <button class="btn btn-default btn-sm" type="submit" name="settings_action" value="generate_api_key"><?php echo $apiConfigured ? 'Rotate key' : 'Generate key'; ?></button>
                    <?php if ($apiConfigured) { ?><button class="btn btn-link btn-sm text-danger" type="submit" name="settings_action" value="revoke_api_key">Revoke</button><?php } ?>
                </div></div>
            </div>
        </div>
        <?php $newApiKey = $CI->session->flashdata('magic_login_new_api_key'); if ($newApiKey) { ?>
            <div class="alert alert-warning"><strong>Copy this key now. It will not be shown again.</strong><br><code class="tw-break-all"><?php echo html_escape($newApiKey); ?></code></div>
        <?php } ?>
    </div>

    <div role="tabpanel" class="tab-pane" id="magic-security">
        <div class="row">
            <div class="col-md-8">
                <div class="checkbox checkbox-warning">
                    <input type="checkbox" id="disable_password_login" name="disable_password_login" value="1" <?php echo get_option('magic_login_disable_password_login') == '1' ? 'checked' : ''; ?>>
                    <label for="disable_password_login">Disable client username-and-password login</label>
                </div>
                <p class="text-muted">This affects client authentication only. Staff/admin login remains unchanged.</p>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" id="altcha_enabled" name="altcha_enabled" value="1" <?php echo get_option('magic_login_altcha_enabled') == '1' ? 'checked' : ''; ?>>
                    <label for="altcha_enabled">Enable automatic ALTCHA protection on authentication forms</label>
                </div>
                <p class="text-muted">The proof-of-work challenge runs at submit time and protects client, passwordless and admin authentication forms.</p>
            </div>
            <div class="col-md-4">
                <div class="panel_s tw-mt-0"><div class="panel-body">
                    <h4 class="tw-font-semibold tw-mt-0">Always-on protections</h4>
                    <ul class="text-muted small tw-pl-4 tw-mb-0">
                        <li>Single-use token redemption</li>
                        <li>Hashed token and API-key storage</li>
                        <li>Same-site redirect validation</li>
                        <li>OTP attempt and request limits</li>
                    </ul>
                </div></div>
            </div>
        </div>
    </div>

    <div role="tabpanel" class="tab-pane" id="magic-updates">
        <div class="row">
            <div class="col-md-8">
                <div class="form-group"><label for="update_policy">Automatic update policy</label><select name="update_policy" id="update_policy" class="form-control">
                    <?php $policy = (string) get_option('magic_login_update_policy'); ?>
                    <option value="off" <?php echo $policy === 'off' ? 'selected' : ''; ?>>Manual updates only</option>
                    <option value="patch" <?php echo $policy === 'patch' ? 'selected' : ''; ?>>Same-major and same-minor releases</option>
                    <option value="safe" <?php echo $policy === 'safe' ? 'selected' : ''; ?>>Only releases marked auto-update safe</option>
                </select></div>
                <p class="text-muted">Unattended installation still requires a valid GitHub release, matching manifest, SHA-256 checksum and contiguous Perfex migration target.</p>
                <button class="btn btn-default" type="submit" name="settings_action" value="check_updates">Check GitHub</button>
                <button class="btn btn-info" type="submit" name="settings_action" value="install_update">Install latest</button>
            </div>
            <div class="col-md-4"><div class="panel_s tw-mt-0"><div class="panel-body">
                <p class="text-muted small tw-mb-1">Installed module</p>
                <p class="tw-font-semibold">v<?php echo html_escape(defined('MAGIC_LOGIN_VERSION') ? MAGIC_LOGIN_VERSION : 'unknown'); ?></p>
                <p class="text-muted small tw-mb-0"><?php echo html_escape((string) get_option('magic_login_last_update_status')); ?></p>
            </div></div></div>
        </div>
        <?php if (!empty($updates)) { ?>
            <hr><h4 class="tw-font-semibold">Recent update history</h4>
            <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Started</th><th>Version</th><th>Mode</th><th>Status</th><th>Details</th></tr></thead><tbody>
            <?php foreach ($updates as $update) { $status = (string) ($update['status'] ?? 'unknown'); ?>
                <tr><td><?php echo e(_dt($update['started_at'])); ?></td><td><?php echo e($update['from_version'] . ' → ' . $update['to_version']); ?></td><td><?php echo !empty($update['automatic']) ? 'Automatic' : 'Manual'; ?></td><td><span class="label <?php echo $status === 'success' ? 'label-success' : ($status === 'failed' ? 'label-danger' : 'label-info'); ?>"><?php echo e(ucfirst($status)); ?></span></td><td class="small"><?php echo e($update['error_message'] ?: '-'); ?></td></tr>
            <?php } ?>
            </tbody></table></div>
        <?php } ?>
    </div>
</div>

<div class="tw-flex tw-items-center tw-justify-between tw-mt-5">
    <p class="text-muted small tw-mb-0">Changes are saved through the module’s admin-only settings handler.</p>
    <button type="submit" class="btn btn-primary"><i class="fa-regular fa-floppy-disk tw-mr-1"></i> Save Magic Login settings</button>
</div>
