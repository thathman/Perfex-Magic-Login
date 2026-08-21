<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Registers Magic Login merge fields for Perfex email templates.
 */

hooks()->add_filter('available_merge_fields', 'magic_login_register_merge_fields');

function magic_login_register_merge_fields($fields)
{
    $fields['magic_login'] = [
        [
            'name' => 'Magic Login URL',
            'key'  => '{magic_login_url}',
        ],
        [
            'name' => 'Magic Login Button',
            'key'  => '{magic_login_button}',
        ],
        [
            'name' => 'Magic Login Expiry',
            'key'  => '{magic_login_expiry}',
        ],
    ];

    return $fields;
}
