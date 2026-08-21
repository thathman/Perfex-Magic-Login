<?php

defined('BASEPATH') or exit('No direct script access allowed');

register_cron_task('magic_login_maybe_auto_update');

function magic_login_maybe_auto_update($manually = false)
{
    $policy = trim((string) get_option('magic_login_update_policy'));
    if (!in_array($policy, ['patch', 'safe'], true)) {
        return;
    }

    $lastCheck = (int) get_option('magic_login_last_auto_update_check');
    if ($lastCheck > 0 && (time() - $lastCheck) < 86400) {
        return;
    }
    update_option('magic_login_last_auto_update_check', (string) time());

    $CI = &get_instance();
    $moduleRow = $CI->db
        ->select('installed_version')
        ->where('module_name', 'magic_login')
        ->get(db_prefix() . 'modules')
        ->row();

    if ($moduleRow && (string) $moduleRow->installed_version !== MAGIC_LOGIN_VERSION) {
        update_option('magic_login_last_update_status', 'Automatic update skipped: a database/module upgrade is already pending.');
        return;
    }

    $CI->load->library('magic_login/Magic_login_updater');
    $release = $CI->magic_login_updater->latest_release(true);
    if (!$release) {
        return;
    }

    if ($policy === 'patch' && !magic_login_same_major_minor(MAGIC_LOGIN_VERSION, $release['version'])) {
        update_option('magic_login_last_update_status', 'Automatic update skipped: v' . $release['version'] . ' is outside the configured patch channel.');
        return;
    }

    $result = $CI->magic_login_updater->install_release($release, true);
    if (!empty($result['ok'])) {
        log_activity('Magic Login automatically updated to v' . $result['version']);
    } else {
        log_message('error', 'Magic Login automatic update skipped/failed: ' . (isset($result['message']) ? $result['message'] : 'unknown error'));
    }
}

function magic_login_same_major_minor($current, $candidate)
{
    $currentParts = explode('.', (string) $current);
    $candidateParts = explode('.', (string) $candidate);

    return isset($currentParts[0], $currentParts[1], $candidateParts[0], $candidateParts[1])
        && $currentParts[0] === $candidateParts[0]
        && $currentParts[1] === $candidateParts[1];
}
