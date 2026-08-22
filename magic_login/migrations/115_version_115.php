<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_115 extends App_module_migration
{
    public function up()
    {
        // File-only release. This target lets Perfex advance installed_version safely.
    }
}
