<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Magic Login
Description: Secure one-click login links and passwordless access for Perfex CRM client contacts
Version: 1.1.2
Requires at least: 3.0.*
*/

define('MAGIC_LOGIN_MODULE', 'magic_login');
define('MAGIC_LOGIN_VERSION', '1.1.2');

/**
 * Return true when Perfex has newer Magic Login files than the module schema
 * recorded in tblmodules. Runtime features should fail closed until the admin
 * runs Setup -> Modules -> Upgrade Database.
 */
function magic_login_database_upgrade_required()
{
    try {
        $CI = &get_instance();
        if (!isset($CI->app_modules)) {
            return false;
        }

        return (bool) $CI->app_modules->is_database_upgrade_required(MAGIC_LOGIN_MODULE);
    } catch (Throwable $e) {
        log_message('error', 'Magic Login database readiness check failed: ' . $e->getMessage());
        return true;
    }
}

require_once __DIR__ . '/hooks/merge_fields.php';
require_once __DIR__ . '/hooks/whatsapp_login.php';
require_once __DIR__ . '/hooks/security.php';
require_once __DIR__ . '/hooks/updates.php';

hooks()->add_action('admin_init', 'magic_login_module_init_menu_items');
hooks()->add_action('admin_init', 'magic_login_permissions');

register_activation_hook(MAGIC_LOGIN_MODULE, 'magic_login_module_activation_hook');
function magic_login_module_activation_hook()
{
    require_once __DIR__ . '/install.php';
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
        $upgradeRequired = magic_login_database_upgrade_required();
        $CI->app_menu->add_sidebar_menu_item('magic-login', [
            'name'     => $upgradeRequired ? 'Magic Login (Upgrade Required)' : 'Magic Login',
            'href'     => $upgradeRequired ? admin_url('modules') : admin_url('magic_login'),
            'position' => 36,
            'icon'     => $upgradeRequired ? 'fa fa-exclamation-triangle' : 'fa fa-link',
        ]);
    }
}
