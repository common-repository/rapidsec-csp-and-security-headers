<?php

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
  die();
}

require_once __DIR__ . '/Rapidsec/config.php';
use Rapidsec\Config;

class Uninstall extends Config
{
  public function __construct()
  {
    $this->rapidsec_delete_option(RAPIDSEC_CONFIG_ADMIN);
    $this->rapidsec_delete_option(RAPIDSEC_CONFIG_FRONTEND);
    $this->rapidsec_delete_option(RAPIDSEC_CONFIG_CHECKOUT);
    $this->rapidsec_delete_option(RAPIDSEC_BANNER_NOTICE_HIDE);
  }
}

new Uninstall();
