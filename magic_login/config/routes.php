<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Module routes are resolved relative to magic_login by MX_Router.
$route['magic_login/link/(:any)'] = 'link/index/$1';

$route['magic_login/whatsapp'] = 'whatsapp/index';
$route['magic_login/whatsapp/request'] = 'whatsapp/request';
$route['magic_login/whatsapp/verify'] = 'whatsapp/verify';

$route['magic_login/api/create-link'] = 'magic_login_api/create_link';
$route['magic_login/api/request-otp'] = 'magic_login_api/request_otp';
$route['magic_login/api/verify-otp'] = 'magic_login_api/verify_otp';
$route['magic_login/api/revoke'] = 'magic_login_api/revoke';
