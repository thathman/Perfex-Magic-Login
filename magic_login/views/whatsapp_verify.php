<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="airix-auth-page airix-auth-page--whatsapp airix-auth-page--verify">
    <aside class="airix-auth-intro" aria-labelledby="airix-whatsapp-verify-title"><div class="airix-auth-intro__inner">
        <div class="airix-auth-brand-row"><div class="airix-auth-brand"><?php get_dark_company_logo('', 'airix-auth-brand__logo'); ?></div><span class="airix-auth-domain">portal.airixmedia.com</span></div>
        <div class="airix-auth-story"><p class="airix-auth-kicker">WhatsApp access · verification</p><h1 id="airix-whatsapp-verify-title">You are nearly there.</h1><p>Enter the six digits from the latest Airix Media message to open your workspace.</p></div>
        <div class="airix-auth-context"><div><span>Keep it private</span><p>Codes expire quickly and can only be used once.</p></div><a href="https://airixmedia.com/support/request" target="_blank" rel="noopener">Need help? <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a></div>
    </div></aside>
    <section class="airix-auth-form" aria-labelledby="whatsapp-verify-heading"><div class="airix-auth-form__inner">
        <div class="airix-auth-form-meta"><span>Airix Media client portal</span><time data-airix-local-date></time></div>
        <div class="airix-auth-welcome"><p class="airix-auth-greeting" data-airix-greeting>Welcome.</p><h2 id="whatsapp-verify-heading" class="login-heading">Enter your code</h2><p>Use the latest six-digit code sent to WhatsApp.</p></div>
        <?= form_open(site_url('magic_login/whatsapp/verify'), ['class' => 'login-form airix-login-form airix-whatsapp-form', 'data-magic-login-ajax' => 'true']); ?>
            <div class="form-group"><label for="code">Login code</label><div class="airix-input-shell"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><input type="text" name="code" id="code" class="form-control airix-code-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required></div></div>
            <?php if (function_exists('magic_login_render_altcha_widget')) { magic_login_render_altcha_widget('airix-altcha-widget'); } ?>
            <div class="airix-auth-feedback" data-magic-login-feedback role="status" aria-live="polite" hidden></div>
            <div class="form-group airix-auth-submit"><button type="submit" class="btn btn-primary btn-block"><span>Verify and sign in</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button></div>
        <?= form_close(); ?>
        <div class="airix-auth-methods"><a class="airix-auth-back" href="<?= site_url('magic_login/whatsapp'); ?>" data-magic-login-cooldown-display="whatsapp"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> <span>Request another code</span></a><a class="airix-auth-secondary" href="<?= site_url('authentication/login'); ?>">Use another method</a></div>
    </div></section>
</div>
