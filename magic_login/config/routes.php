<?php

defined('BASEPATH') or exit('No direct script access allowed');

$route['magic_login/link/(:any)'] = 'magic_login/link/index/$1';

$route['magic_login/whatsapp'] = 'magic_login/whatsapp/index';
$route['magic_login/whatsapp/request'] = 'magic_login/whatsapp/request';
$route['magic_login/whatsapp/verify'] = 'magic_login/whatsapp/verify';

$route['magic_login/api/create-link'] = 'magic_login/magic_login_api/create_link';
$route['magic_login/api/request-otp'] = 'magic_login/magic_login_api/request_otp';
$route['magic_login/api/verify-otp'] = 'magic_login/magic_login_api/verify_otp';
$route['magic_login/api/revoke'] = 'magic_login/magic_login_api/revoke';
