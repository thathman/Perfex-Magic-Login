<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_111 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $otpTable = db_prefix() . 'magic_login_otps';
        $updatesTable = db_prefix() . 'magic_login_updates';

        if (!$CI->db->table_exists($otpTable)) {
            $CI->db->query('CREATE TABLE `' . $otpTable . "` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `contact_id` INT UNSIGNED NULL,
                `request_hash` CHAR(64) NOT NULL,
                `code_hash` VARCHAR(255) NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5,
                `used_at` DATETIME NULL,
                `requested_ip` VARCHAR(45) NULL,
                `delivery_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                `sent_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `request_hash_unique` (`request_hash`),
                KEY `contact_id` (`contact_id`),
                KEY `requested_ip` (`requested_ip`),
                KEY `expires_at` (`expires_at`),
                KEY `created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
        }

        if (!$CI->db->table_exists($updatesTable)) {
            $CI->db->query('CREATE TABLE `' . $updatesTable . "` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `from_version` VARCHAR(32) NOT NULL,
                `to_version` VARCHAR(32) NOT NULL,
                `release_tag` VARCHAR(64) NULL,
                `checksum` CHAR(64) NULL,
                `automatic` TINYINT(1) NOT NULL DEFAULT 0,
                `status` VARCHAR(20) NOT NULL DEFAULT 'started',
                `error_message` TEXT NULL,
                `backup_path` VARCHAR(500) NULL,
                `started_at` DATETIME NOT NULL,
                `completed_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `status` (`status`),
                KEY `started_at` (`started_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
        }

        add_option('magic_login_whatsapp_enabled', '0');
        add_option('magic_login_whatsapp_api_url', '');
        add_option('magic_login_whatsapp_api_token', '');
        add_option('magic_login_whatsapp_message', 'Your {company} login code is {code}. It expires in {minutes} minutes.');
        add_option('magic_login_otp_expiry_minutes', '5');
        add_option('magic_login_otp_max_attempts', '5');
        add_option('magic_login_api_enabled', '0');
        add_option('magic_login_api_key_hash', '');
        add_option('magic_login_update_policy', 'off');
        add_option('magic_login_release_cache', '');
        add_option('magic_login_release_cache_checked_at', '0');
        add_option('magic_login_last_auto_update_check', '0');
        add_option('magic_login_last_update_status', 'Never checked.');
    }
}
