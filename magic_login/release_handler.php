<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('magic_login_run_github_update')) {
    function magic_login_run_github_update($unused = null)
    {
        $CI = &get_instance();
        $CI->load->library('magic_login/Magic_login_updater');
        $result = $CI->magic_login_updater->install_latest(false);

        if (!empty($result['ok'])) {
            set_alert('success', $result['message']);
        } else {
            set_alert('danger', isset($result['message']) ? $result['message'] : 'Magic Login update failed.');
        }
    }
}

$CI = &get_instance();
$CI->load->library('magic_login/Magic_login_updater');
$release = $CI->magic_login_updater->latest_release(false);

if (!$release) {
    return false;
}

return [
    'version'        => $release['version'],
    'changelog'      => $release['changelog'],
    'update_handler' => 'magic_login_run_github_update',
];
