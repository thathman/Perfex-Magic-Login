<?php

defined('BASEPATH') or exit('No direct script access allowed');

hooks()->add_action('clients_authentication_constructor', 'magic_login_guard_client_auth');
hooks()->add_action('admin_auth_init', 'magic_login_guard_admin_auth');
hooks()->add_action('before_admin_login_form_close', 'magic_login_render_admin_altcha');
hooks()->add_action('app_customers_head', 'magic_login_render_customer_altcha_assets');
hooks()->add_action('app_admin_authentication_head', 'magic_login_render_admin_altcha_assets');

function magic_login_altcha_enabled()
{
    return (int) get_option('magic_login_altcha_enabled') === 1;
}

function magic_login_altcha_challenge_url()
{
    return site_url('magic_login/altcha/challenge');
}

function magic_login_render_altcha_widget($class = '')
{
    if (!magic_login_altcha_enabled()) {
        return;
    }

    echo '<altcha-widget class="' . html_escape($class) . '" name="altcha" challengeurl="'
        . html_escape(magic_login_altcha_challenge_url())
        . '" auto="onsubmit" display="floating" hidefooter="true"></altcha-widget>';
}

function magic_login_render_customer_altcha_assets()
{
    $uri = trim((string) get_instance()->uri->uri_string(), '/');
    if (!magic_login_altcha_enabled() || !preg_match('#^(authentication/(login|register|forgot_password)|magic_login/(request|whatsapp))(?:/|$)#i', $uri)) {
        return;
    }

    echo '<script type="module" async defer src="https://cdn.jsdelivr.net/npm/altcha' . chr(64) . '1.0.5/dist/altcha.min.js"></script>';
}

function magic_login_render_admin_altcha_assets()
{
    if (!magic_login_altcha_enabled()) {
        return;
    }

    $challenge = html_escape(magic_login_altcha_challenge_url());
    echo '<script type="module" async defer src="https://cdn.jsdelivr.net/npm/altcha' . chr(64) . '1.0.5/dist/altcha.min.js"></script>';
    echo '<script>(function(){document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("form").forEach(function(form){if(form.querySelector("altcha-widget")){return;}var widget=document.createElement("altcha-widget");widget.setAttribute("name","altcha");widget.setAttribute("challengeurl","' . $challenge . '");widget.setAttribute("auto","onsubmit");widget.setAttribute("display","floating");widget.setAttribute("hidefooter","true");widget.style.display="none";form.appendChild(widget);});});}());</script>';
}

function magic_login_render_admin_altcha()
{
    magic_login_render_altcha_widget('airix-altcha-widget airix-altcha-widget--admin');
}

function magic_login_guard_client_auth($controller)
{
    if (!$controller->input->post()) {
        return;
    }

    $uri = trim((string) $controller->uri->uri_string(), '/');
    if (!preg_match('#^authentication/(login|register|forgot_password|reset_password(?:/|$))#i', $uri)) {
        return;
    }

    if (magic_login_altcha_enabled() && !magic_login_verify_altcha((string) $controller->input->post('altcha', false))) {
        set_alert('warning', 'Please complete the security check and try again.');
        redirect(site_url($uri));
    }

    if ($uri === 'authentication/login' && (int) get_option('magic_login_disable_password_login') === 1) {
        set_alert('info', 'Password login is disabled. Use a secure email link or WhatsApp code.');
        redirect(site_url('authentication/login'));
    }
}

function magic_login_guard_admin_auth()
{
    $CI = &get_instance();
    if (!$CI->input->post() || !magic_login_altcha_enabled()) {
        return;
    }

    if (!magic_login_verify_altcha((string) $CI->input->post('altcha', false))) {
        set_alert('warning', 'Please complete the security check and try again.');
        redirect(admin_url('authentication'));
    }
}

function magic_login_verify_altcha($payload)
{
    $CI = &get_instance();
    $CI->load->library('magic_login/Magic_login_altcha');
    return $CI->magic_login_altcha->verify($payload);
}
