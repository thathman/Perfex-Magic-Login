<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_110 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'magic_login_tokens';
        $auditTable = db_prefix() . 'magic_login_audit';

        if (!$CI->db->table_exists($table)) {
            require module_dir_path('magic_login', 'install.php');
            return;
        }

        if (!$CI->db->field_exists('revoked_at', $table)) {
            $CI->db->query("ALTER TABLE `{$table}` ADD `revoked_at` DATETIME NULL AFTER `used_at`");
        }
        if (!$CI->db->field_exists('revoked_by', $table)) {
            $CI->db->query("ALTER TABLE `{$table}` ADD `revoked_by` INT UNSIGNED NULL AFTER `revoked_at`");
        }

        $CI->db->query("ALTER TABLE `{$table}` MODIFY `created_by` INT UNSIGNED NULL");

        if (!$CI->db->field_exists('source', $table)) {
            $CI->db->query("ALTER TABLE `{$table}` ADD `source` VARCHAR(30) NOT NULL DEFAULT 'manual' AFTER `created_by`");
        }
        if (!$CI->db->field_exists('context_type', $table)) {
            $CI->db->query("ALTER TABLE `{$table}` ADD `context_type` VARCHAR(50) NULL AFTER `source`");
        }
        if (!$CI->db->field_exists('context_id', $table)) {
            $CI->db->query("ALTER TABLE `{$table}` ADD `context_id` INT UNSIGNED NULL AFTER `context_type`");
        }
        if (!$CI->db->field_exists('redirect_path', $table)) {
            $CI->db->query("ALTER TABLE `{$table}` ADD `redirect_path` VARCHAR(500) NULL AFTER `context_id`");
        } else {
            $CI->db->query("ALTER TABLE `{$table}` MODIFY `redirect_path` VARCHAR(500) NULL");
        }
        if (!$CI->db->field_exists('used_ip', $table)) {
            $CI->db->query("ALTER TABLE `{$table}` ADD `used_ip` VARCHAR(45) NULL AFTER `redirect_path`");
        }
        if (!$CI->db->field_exists('used_user_agent', $table)) {
            $CI->db->query("ALTER TABLE `{$table}` ADD `used_user_agent` VARCHAR(255) NULL AFTER `used_ip`");
        }

        $index = $CI->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = 'token_hash_unique'");
        if ($index && $index->num_rows() === 0) {
            $CI->db->query("ALTER TABLE `{$table}` ADD UNIQUE KEY `token_hash_unique` (`token_hash`)");
        }

        if (!$CI->db->table_exists($auditTable)) {
            $CI->db->query('CREATE TABLE `' . $auditTable . "` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `token_id` INT UNSIGNED NULL,
                `contact_id` INT UNSIGNED NULL,
                `event` VARCHAR(50) NOT NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` VARCHAR(255) NULL,
                `metadata` TEXT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `token_id` (`token_id`),
                KEY `contact_id` (`contact_id`),
                KEY `event` (`event`),
                KEY `created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
        }

        add_option('magic_login_default_expiry_minutes', '60');
        add_option('magic_login_auto_secure_email_links', '0');
    }
}
