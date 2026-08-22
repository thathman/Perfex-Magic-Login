<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="airix-auth-page airix-auth-page--magic">
    <aside class="airix-auth-intro" aria-labelledby="airix-magic-title">
        <div class="airix-auth-intro__inner">
            <div class="airix-auth-brand-row">
                <div class="airix-auth-brand"><?php get_dark_company_logo('', 'airix-auth-brand__logo'); ?></div>
                <span class="airix-auth-domain">portal.airixmedia.com</span>
            </div>
            <div class="airix-auth-story">
                <p class="airix-auth-kicker">Secure access · no password required</p>
                <h1 id="airix-magic-title">Open the work without the wait.</h1>
                <p>We will send a one-time sign-in link to the email already connected to your client workspace.</p>
            </div>
            <div class="airix-auth-context">
                <div><span>Your workspace</span><p>One secure link. One clear way back to the work.</p></div>
                <a href="https://airixmedia.com" target="_blank" rel="noopener">Visit Airix Media <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
            </div>
        </div>
    </aside>
    <section class="airix-auth-form" aria-labelledby="magic-login-heading">
        <div class="airix-auth-form__inner">
            <div class="airix-auth-form-meta"><span>Airix Media client portal</span><time data-airix-local-date></time></div>
            <div class="airix-auth-welcome"><p class="airix-auth-greeting" data-airix-greeting>Welcome.</p><h2 id="magic-login-heading" class="login-heading">Email me a secure link</h2><p>It expires shortly and works only once.</p></div>
            <?= form_open(site_url('magic_login/request/send'), ['class' => 'login-form airix-login-form airix-magic-form', 'data-magic-login-ajax' => 'true', 'data-magic-login-cooldown' => 'email']); ?>
                <div class="form-group"><label for="email">Email address</label><div class="airix-input-shell"><i class="fa-regular fa-envelope" aria-hidden="true"></i><input type="email" name="email" id="email" class="form-control" autocomplete="email" placeholder="name@organisation.com" required></div></div>
                <?php if (function_exists('magic_login_render_altcha_widget')) { magic_login_render_altcha_widget('airix-altcha-widget'); } ?>
                <div class="airix-auth-feedback" data-magic-login-feedback role="status" aria-live="polite" hidden></div>
                <div class="form-group airix-auth-submit"><button type="submit" class="btn btn-primary btn-block"><span>Send secure link</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button></div>
            <?= form_close(); ?>
            <div class="airix-auth-methods"><a class="airix-auth-back" href="<?= site_url('authentication/login'); ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to sign in</a><a class="airix-auth-secondary" href="<?= site_url('magic_login/whatsapp'); ?>">Use WhatsApp instead</a></div>
        </div>
    </section>
</div>
