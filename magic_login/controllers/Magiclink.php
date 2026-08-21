<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Backwards-compatible endpoint for v1.0.0 query-string links.
 */
class Magiclink extends App_Controller
{
    public function index()
    {
        $token = trim((string) $this->input->get('token', true));
        if ($token === '') {
            show_error('Invalid magic login link.', 400);
            return;
        }

        redirect(site_url('magic_login/link/' . rawurlencode($token)));
    }
}
