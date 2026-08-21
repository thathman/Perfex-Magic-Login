<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Security foundation migration.
 */
function magic_login_migration_001()
{
    $CI = &get_instance();
    $table = db_prefix() . 'magic_login_tokens';

    if (!$CI->db->field_exists('revoked_at', $table)) {
        $CI->db->query("ALTER TABLE `{$table}` ADD `revoked_at` DATETIME NULL AFTER `used_at`");
    }

    if (!$CI->db->field_exists('revoked_by', $table)) {
        $CI->db->query("ALTER TABLE `{$table}` ADD `revoked_by` INT NULL AFTER `revoked_at`");
    }

    if (!$CI->db->field_exists('source', $table)) {
        $CI->db->query("ALTER TABLE `{$table}` ADD `source` VARCHAR(30) NOT NULL DEFAULT 'manual' AFTER `created_by`");
    }

    if (!$CI->db->field_exists('context_type', $table)) {
        $CI->db->query("ALTER TABLE `{$table}` ADD `context_type` VARCHAR(50) NULL AFTER `source`");
    }

    if (!$CI->db->field_exists('context_id', $table)) {
        $CI->db->query("ALTER TABLE `{$table}` ADD `context_id` INT NULL AFTER `context_type`");
    }

    if (!$CI->db->field_exists('used_ip', $table)) {
        $CI->db->query("ALTER TABLE `{$table}` ADD `used_ip` VARCHAR(45) NULL AFTER `context_id`");
    }
}
