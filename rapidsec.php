<?php
/**
 * RapidSec
 *
 * @package rapidsec
 * @author rapidsec
 * @license GPL-3.0-or-later
 *
 * Plugin Name:       RapidSec - CSP and Security Headers
 * Description:       RapidSec fully automates your client-side security and CSP (content security policy), using comprehensive telemetry and AI-driven insights out of the box.
 * Version:           1.3.4
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * License:           GPL v3 or later
 * Author:            RapidSec.com
 * Author URI:        https://rapidsec.com/
 */

defined('ABSPATH') || die('No script kiddies please!');

// Define Config Admin Key
if (!defined('RAPIDSEC_CONFIG_ADMIN')):
  define('RAPIDSEC_CONFIG_ADMIN', 'rapidsec_config_admin');
endif;

// Define Config Frontend Key
if (!defined('RAPIDSEC_CONFIG_FRONTEND')):
  define('RAPIDSEC_CONFIG_FRONTEND', 'rapidsec_config_frontend');
endif;

// Define Config Checkout Key
if (!defined('RAPIDSEC_CONFIG_CHECKOUT')):
  define('RAPIDSEC_CONFIG_CHECKOUT', 'rapidsec_config_checkout');
endif;

// Define Admin Notice
if (!defined('RAPIDSEC_ADMIN_NOTICE_ACTIVATION')):
  define('RAPIDSEC_ADMIN_NOTICE_ACTIVATION', 'rapidsec_admin_notice_activation');
endif;

// Define Admin Notice
if (!defined('RAPIDSEC_CACHE_ADMIN_NOTICE')):
  define('RAPIDSEC_CACHE_ADMIN_NOTICE', 'rapidsec_cache_admin_notice');
endif;

// Define Banner Notice
if (!defined('RAPIDSEC_BANNER_NOTICE_HIDE')):
  define('RAPIDSEC_BANNER_NOTICE_HIDE', 'rapidsec_banner_notice_hide');
endif;

// Define Connector Type
if (!defined('RAPIDSEC_CONNECTOR_CONFIG')):
  define('RAPIDSEC_CONNECTOR_CONFIG', 'rapidsec_connector_config');
endif;

if (!defined('RAPIDSEC__FILE__')):
  define('RAPIDSEC__FILE__', __FILE__);
endif;

if (!defined('RAPIDSEC_PLUGIN_BASE')):
  define('RAPIDSEC_PLUGIN_BASE', plugin_basename(RAPIDSEC__FILE__));
endif;

if (!defined('RAPIDSEC_PATH')):
  define('RAPIDSEC_PATH', plugin_dir_path(RAPIDSEC__FILE__));
endif;

require_once __DIR__ . '/Rapidsec/Core.php';
use Rapidsec\Core;

$core = new Core(__FILE__);
