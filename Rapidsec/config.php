<?php
/**
 * The plugin config file
 *
 * @package Rapidsec
 */

namespace Rapidsec;

defined('ABSPATH') || die('No script kiddies please!');

/**
 * The config settings class
 */
class Config
{
  public static function rapidsec_get_option(string $key)
  {
    return get_option($key);
  }

  public static function rapidsec_update_option(string $key, $val)
  {
    update_option($key, $val);
  }

  public static function rapidsec_delete_option(string $key)
  {
    delete_option($key);
  }
}
