<?php

defined('BASEPATH') or exit('No direct script access allowed');

hooks()->add_action('clients_login_form_end', 'magic_login_render_whatsapp_login_link');

function magic_login_render_whatsapp_login_link()
{
    if ((int) get_option('magic_login_whatsapp_enabled') !== 1) {
        return;
    }

    echo '<div class="text-center mtop20">';
    echo '<div class="text-muted mbot10">or</div>';
    echo '<a class="btn btn-default btn-block" href="' . html_escape(site_url('magic_login/whatsapp')) . '">';
    echo '<i class="fa fa-whatsapp"></i> Continue with WhatsApp';
    echo '</a>';
    echo '</div>';
}
