<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

if (!$CI->db->table_exists(db_prefix() . 'magic_login_tokens')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "magic_login_tokens` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `contact_id` INT UNSIGNED NOT NULL,
        `token_hash` VARCHAR(128) NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `used_at` DATETIME NULL,
        `created_by` INT UNSIGNED NOT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `contact_id` (`contact_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}
