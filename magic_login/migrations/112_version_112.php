<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_112 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();
        $table = db_prefix() . 'magic_login_tokens';

        if (!$CI->db->table_exists($table)) {
            return;
        }

        $existing = [];
        foreach ($CI->db->query('SHOW INDEX FROM `' . $table . '`')->result_array() as $index) {
            $existing[(string) $index['Key_name']] = true;
        }

        $indexes = [
            'expires_at'        => '`expires_at`',
            'created_at'        => '`created_at`',
            'source_created_at' => '`source`, `created_at`',
            'status_lookup'     => '`revoked_at`, `used_at`, `expires_at`',
            'used_at'           => '`used_at`',
            'context_lookup'    => '`context_type`, `context_id`',
        ];

        foreach ($indexes as $name => $columns) {
            if (!isset($existing[$name])) {
                $CI->db->query('ALTER TABLE `' . $table . '` ADD KEY `' . $name . '` (' . $columns . ')');
            }
        }
    }
}
