<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Altcha extends App_Controller
{
    public function challenge()
    {
        $this->load->library('magic_login/Magic_login_altcha');
        $this->output
            ->set_content_type('application/json')
            ->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')
            ->set_output(json_encode($this->magic_login_altcha->challenge()));
    }
}
