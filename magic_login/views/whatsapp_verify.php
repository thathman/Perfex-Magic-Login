<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="mtop40">
  <div class="col-md-4 col-md-offset-4 text-center">
    <h1 class="tw-font-semibold mbot20 login-heading">Verify WhatsApp Code</h1>
  </div>
  <div class="col-md-4 col-md-offset-4 col-sm-8 col-sm-offset-2">
    <div class="panel_s">
      <div class="panel-body">
        <p class="text-muted">Enter the 6-digit code sent to your WhatsApp number.</p>
        <?php echo form_open(site_url('magic_login/whatsapp/verify')); ?>
          <div class="form-group">
            <label for="code">Login code</label>
            <input type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" class="form-control text-center" name="code" id="code" required>
          </div>
          <button type="submit" class="btn btn-success btn-block">
            Verify and sign in
          </button>
        <?php echo form_close(); ?>
        <div class="text-center mtop20">
          <a href="<?php echo site_url('magic_login/whatsapp'); ?>">Request another code</a>
          &nbsp;&middot;&nbsp;
          <a href="<?php echo site_url('authentication/login'); ?>">Password login</a>
        </div>
      </div>
    </div>
  </div>
</div>
