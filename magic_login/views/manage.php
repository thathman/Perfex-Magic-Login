<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-5">
        <div class="panel_s"><div class="panel-body">
          <h4 class="no-margin">Create Magic Login Link</h4>
          <hr class="hr-panel-heading" />
          <?php $link = $this->session->flashdata('magic_login_link'); if ($link) { ?>
            <div class="alert alert-success"><strong>Link:</strong> <code><?php echo html_escape($link); ?></code></div>
          <?php } ?>
          <form method="post" action="<?php echo admin_url('magic_login/create'); ?>">
            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
            <div class="form-group">
              <label for="contact_id">Contact</label>
              <select class="form-control" name="contact_id" id="contact_id" required>
                <option value="">Select contact</option>
                <?php foreach ($contacts as $c) { ?>
                  <option value="<?php echo (int)$c['id']; ?>"><?php echo html_escape($c['company'] . ' - ' . $c['firstname'] . ' ' . $c['lastname'] . ' (' . $c['email'] . ')'); ?></option>
                <?php } ?>
              </select>
            </div>
            <?php echo render_input('hours', 'Validity (hours)', '24', 'number'); ?>
            <div class="form-group">
              <label for="portal_endpoint">Client Portal Destination</label>
              <select class="form-control" name="portal_endpoint" id="portal_endpoint">
                <?php foreach (($endpoint_options ?? []) as $endpointValue => $endpointLabel) { ?>
                  <option value="<?php echo html_escape($endpointValue); ?>" <?php echo $endpointValue === 'vault/client' ? 'selected' : ''; ?>>
                    <?php echo html_escape($endpointLabel); ?>
                  </option>
                <?php } ?>
              </select>
            </div>
            <div class="form-group" id="custom-endpoint-wrap" style="display:none;">
              <label for="custom_endpoint">Custom Endpoint (relative path)</label>
              <input type="text" class="form-control" name="custom_endpoint" id="custom_endpoint" placeholder="clients/invoices">
            </div>
            <div class="checkbox checkbox-primary">
              <input type="checkbox" id="send_email" name="send_email" value="1" checked>
              <label for="send_email">Send link to contact email immediately</label>
            </div>
            <button class="btn btn-primary" type="submit">Generate Link</button>
          </form>
        </div></div>
      </div>
      <div class="col-md-7">
        <div class="panel_s"><div class="panel-body">
          <h4 class="no-margin">Recent Tokens</h4>
          <hr class="hr-panel-heading" />
          <table class="table table-striped">
            <thead><tr><th>Contact</th><th>Company</th><th>Destination</th><th>Expires</th><th>Used</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($tokens as $t) { ?>
                <tr>
                  <td><?php echo html_escape($t['firstname'] . ' ' . $t['lastname'] . ' (' . $t['email'] . ')'); ?></td>
                  <td><?php echo html_escape((string)$t['company']); ?></td>
                  <td><?php echo html_escape(!empty($t['redirect_path']) ? site_url($t['redirect_path']) : site_url('clients')); ?></td>
                  <td><?php echo html_escape((string)$t['expires_at']); ?></td>
                  <td><?php echo html_escape((string)$t['used_at']); ?></td>
                  <td><?php if (empty($t['used_at'])) { ?><a class="btn btn-danger btn-sm" href="<?php echo admin_url('magic_login/revoke/' . (int)$t['id']); ?>">Revoke</a><?php } ?></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div></div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>
<script>
(function () {
  var endpoint = document.getElementById('portal_endpoint');
  var customWrap = document.getElementById('custom-endpoint-wrap');
  if (!endpoint || !customWrap) return;
  function syncCustom() {
    customWrap.style.display = endpoint.value === 'custom' ? '' : 'none';
  }
  endpoint.addEventListener('change', syncCustom);
  syncCustom();
})();
</script>
