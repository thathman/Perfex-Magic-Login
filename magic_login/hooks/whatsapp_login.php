<?php

defined('BASEPATH') or exit('No direct script access allowed');

hooks()->add_action('clients_login_form_end', 'magic_login_render_whatsapp_login_link');

function magic_login_render_whatsapp_login_link()
{
    if (function_exists('magic_login_database_upgrade_required') && magic_login_database_upgrade_required()) {
        return;
    }

    if ((int) get_option('magic_login_whatsapp_enabled') !== 1) {
        return;
    }

    echo '<div class="airix-auth-method-block">';
    echo '<div class="airix-auth-divider"><span>or continue without a password</span></div>';
    echo '<a class="airix-auth-method airix-auth-method--whatsapp" href="' . html_escape(site_url('magic_login/whatsapp')) . '">';
    echo '<span class="airix-auth-method__icon" aria-hidden="true"><i class="fa-brands fa-whatsapp"></i></span>';
    echo '<span><strong>Continue with WhatsApp</strong><small>Use a one-time code sent to your phone</small></span>';
    echo '<i class="fa-solid fa-arrow-right airix-auth-method__arrow" aria-hidden="true"></i>';
    echo '</a>';
    echo '</div>';
}
