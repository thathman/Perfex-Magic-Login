<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$tokensTable = db_prefix() . 'magic_login_tokens';
$auditTable  = db_prefix() . 'magic_login_audit';

if (!$CI->db->table_exists($tokensTable)) {
    $CI->db->query('CREATE TABLE `' . $tokensTable . "` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `contact_id` INT UNSIGNED NOT NULL,
        `token_hash` CHAR(64) NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `used_at` DATETIME NULL,
        `revoked_at` DATETIME NULL,
        `revoked_by` INT UNSIGNED NULL,
        `created_by` INT UNSIGNED NULL,
        `source` VARCHAR(30) NOT NULL DEFAULT 'manual',
        `context_type` VARCHAR(50) NULL,
        `context_id` INT UNSIGNED NULL,
        `redirect_path` VARCHAR(500) NULL,
        `used_ip` VARCHAR(45) NULL,
        `used_user_agent` VARCHAR(255) NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `token_hash_unique` (`token_hash`),
        KEY `contact_id` (`contact_id`),
        KEY `expires_at` (`expires_at`),
        KEY `context_lookup` (`context_type`,`context_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
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
