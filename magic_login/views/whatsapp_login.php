<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="airix-auth-page airix-auth-page--whatsapp">
    <aside class="airix-auth-intro" aria-labelledby="airix-whatsapp-title"><div class="airix-auth-intro__inner">
        <div class="airix-auth-brand-row"><div class="airix-auth-brand"><?php get_dark_company_logo('', 'airix-auth-brand__logo'); ?></div><span class="airix-auth-domain">portal.airixmedia.com</span></div>
        <div class="airix-auth-story"><p class="airix-auth-kicker">WhatsApp access · one-time code</p><h1 id="airix-whatsapp-title">A direct line back to the work.</h1><p>Use the phone number connected to your Airix Media workspace and we will send a short-lived code.</p></div>
        <div class="airix-auth-context"><div><span>Your workspace</span><p>Fast, private access without remembering another password.</p></div><a href="https://airixmedia.com" target="_blank" rel="noopener">Visit Airix Media <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a></div>
    </div></aside>
    <section class="airix-auth-form" aria-labelledby="whatsapp-login-heading"><div class="airix-auth-form__inner">
        <div class="airix-auth-form-meta"><span>Airix Media client portal</span><time data-airix-local-date></time></div>
        <div class="airix-auth-welcome"><p class="airix-auth-greeting" data-airix-greeting>Welcome.</p><h2 id="whatsapp-login-heading" class="login-heading">Continue with WhatsApp</h2><p>Your code will be delivered to the number on your client account.</p></div>
        <?= form_open(site_url('magic_login/whatsapp/request'), ['class' => 'login-form airix-login-form airix-whatsapp-form']); ?>
            <div class="form-group"><label for="phone">WhatsApp number</label><div class="airix-input-shell"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i><input type="tel" name="phone" id="phone" class="form-control" autocomplete="tel" inputmode="tel" placeholder="+234 801 234 5678" required></div><small class="airix-field-help">Include the country code.</small></div>
            <?php if (function_exists('magic_login_render_altcha_widget')) { magic_login_render_altcha_widget('airix-altcha-widget'); } ?>
            <div class="form-group airix-auth-submit"><button type="submit" class="btn btn-primary btn-block"><span>Send login code</span><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></button></div>
        <?= form_close(); ?>
        <div class="airix-auth-methods"><a class="airix-auth-back" href="<?= site_url('authentication/login'); ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to sign in</a><a class="airix-auth-secondary" href="<?= site_url('magic_login/request'); ?>">Email me a secure link</a></div>
    </div></section>
</div>
