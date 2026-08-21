<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="mtop40">
  <div class="col-md-4 col-md-offset-4 text-center">
    <h1 class="tw-font-semibold mbot20 login-heading">Login with WhatsApp</h1>
  </div>
  <div class="col-md-4 col-md-offset-4 col-sm-8 col-sm-offset-2">
    <div class="panel_s">
      <div class="panel-body">
        <p class="text-muted">Enter the WhatsApp number saved on your client account. Include the country code.</p>
        <?php echo form_open(site_url('magic_login/whatsapp/request')); ?>
          <div class="form-group">
            <label for="phone">WhatsApp number</label>
            <input type="tel" class="form-control" name="phone" id="phone" autocomplete="tel" placeholder="+2348012345678" required>
          </div>
          <button type="submit" class="btn btn-success btn-block">
            <i class="fa fa-whatsapp"></i> Send login code
          </button>
        <?php echo form_close(); ?>
        <div class="text-center mtop20">
          <a href="<?php echo site_url('authentication/login'); ?>">Back to password login</a>
        </div>
      </div>
    </div>
  </div>
</div>
