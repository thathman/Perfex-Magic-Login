<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Magic Login
Description: One-click magic login links for client contacts
Version: 1.0.0
Requires at least: 3.0.*
*/

define('MAGIC_LOGIN_MODULE', 'magic_login');

hooks()->add_action('admin_init', 'magic_login_module_init_menu_items');
hooks()->add_action('admin_init', 'magic_login_permissions');

register_activation_hook(MAGIC_LOGIN_MODULE, 'magic_login_module_activation_hook');
function magic_login_module_activation_hook()
{
    require_once(__DIR__ . '/install.php');
}

function magic_login_permissions()
{
    $capabilities = [];
    $capabilities['capabilities'] = [
        'view'   => _l('permission_view'),
        'create' => _l('permission_create'),
        'delete' => _l('permission_delete'),
    ];
    register_staff_capabilities('magic_login', $capabilities, 'Magic Login');
}

function magic_login_module_init_menu_items()
{
    $CI = &get_instance();
    if (staff_can('view', 'magic_login') || is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('magic-login', [
            'name'     => 'Magic Login',
            'href'     => admin_url('magic_login'),
            'position' => 36,
            'icon'     => 'fa fa-link',
        ]);
    }
}
