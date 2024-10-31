<?php
/**
 * The core plugin
 *
 * @package Rapidsec
 */

declare(strict_types=1);

namespace Rapidsec;

require_once 'config.php';
require_once 'connectors.php';
require_once 'RapidsecApi.php';

require_once 'AdditionalPlugins.php';

if (!defined('ABSPATH')) {
  exit();
}

/**
 * The CSP core class
 * @since 1.0.0
 */
class Core
{
  /**
   * @var Settings
   */
  protected $settings;

  /**
   * @var string
   */
  public $version;

  /**
   * @var PluginAdditions
   */
  public $pluginAdditions;

  /**
   * @var RapidsecApi
   */
  public $api;
  /**
   * @var Connector
   */
  public $connector;

  /**
   * Set up actions and hooks
   *
   * @param string $pluginfile __FILE__ path to the main plugin file.
   *
   * @since 1.0.0
   */
  public function __construct(string $pluginfile)
  {
    if (!function_exists('get_plugin_data')) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $this->version = get_plugin_data($pluginfile)['Version'];

    $this->pluginAdditions = new PluginAdditions($this->version);
    $this->connector = new Connector($this->version);
    $this->api = new RapidsecApi($this->version);

    if (is_admin() && !wp_doing_ajax()) {
      require_once __DIR__ . '/Settings.php';
      $this->settings = new Settings($pluginfile, $this);
    }

    add_action('init', [$this, 'csp_init']);

    add_action('wp_ajax_rapidsec_test_token', [$this, 'rapidsec_test_token_callback']);
    add_action('wp_ajax_rapidsec_clear_cache', [$this, 'rapidsec_clear_cache_callback']);
    add_action('upgrader_process_complete', [$this, 'rapidsec_upgrade_process'], 10, 2);
    add_action('wp_ajax_rapidsec_banner_time', [$this, 'rapidsec_banner_time_callback']);
    add_action('wp_ajax_rapidsec_plugin_deactivate', [$this, 'rapidsec_plugin_deactivate_callback']);

    #Filters
    add_filter('plugin_action_links_' . plugin_basename($pluginfile), [$this, 'add_action_links']);
  }

  /**
   * Output CSP headers in send_headers
   */
  public function csp_init(string $pluginfile): void
  {
    [$type, $option] = $this->get_option();

    if ($option['token'] == false) {
      return;
    }

    $cspConfig = $this->get_csp_config();

    if (empty($cspConfig->headers)) {
      return;
    }

    $result = $this->connector->apply_on_request($cspConfig->headers, $option['token']);

    if (!empty($result)) {
      $this->api->rapidsec_send_report($option['token'], $result);
    }
  }

  /**
   * @return string[]
   */
  private function get_option(): array
  {
    if (is_admin()) {
      // Admin
      return ['admin', Config::rapidsec_get_option(RAPIDSEC_CONFIG_ADMIN)];
    }

    $pluginOption = $this->pluginAdditions->get_custom_token();

    if ($pluginOption !== false) {
      return $pluginOption;
    }

    return ['frontend', Config::rapidsec_get_option(RAPIDSEC_CONFIG_FRONTEND)];
  }

  private function get_csp_config(): ?ConfigRemoteDTO
  {
    [$type, $option] = $this->get_option();

    $existingConfigKey = sprintf('rapidsec_cached_%s', md5($option['token']));
    $pullingKey = sprintf('%s_pulled', $existingConfigKey);
    $cspIsPulled = get_transient($pullingKey);
    $cspConfig = get_transient($existingConfigKey);
    $pollInterval = $option['polling_interval'];

    if ($cspIsPulled == true) {
      if ($cspConfig instanceof ConfigRemoteDTO) {
        return $cspConfig;
      } elseif (!$cspConfig) {
        return null;
      }

      return new ConfigRemoteDTO($cspConfig);
    }

    // TODO: find how to do this asynchronous
    set_transient($pullingKey, true, $pollInterval ?? 15);

    $body = $this->api->get_csp($option['token']);

    $isNewCspVersion = $body->reportCspVersion != $cspConfig->reportCspVersion;
    $isNewPluginVersion = $cspConfig->version !== $this->version;

    if (isset($body->headers) or $cspConfig == false or $isNewCspVersion or $isNewPluginVersion) {
      $body->version = $this->version;
      // Update version in report path
      foreach ((array) $body->headers as $header) {
        $versionStr = sprintf('sdkv=%s_agent-wordpress', $this->version);
        if (strpos($header->value, 'sdkv=-1.-1.-1_unknown')) {
          $header->value = str_replace('sdkv=-1.-1.-1_unknown', sprintf('%s', $versionStr), $header->value);
        } else {
          $header->value = str_replace('sct=', sprintf('%s&sct=', $versionStr), $header->value);
        }
      }

      set_transient($existingConfigKey, $body, 60 * 60 * 24 * 30);

      $this->connector->apply_on_new_config($body, $option['token'], $type);

      return $body;
    }

    if ($cspConfig instanceof ConfigRemoteDTO) {
      return $cspConfig;
    }

    return new ConfigRemoteDTO($cspConfig);
  }

  public function get_all_transients()
  {
    global $wpdb;
    $table = $wpdb->prefix . 'options';
    $all_results = $wpdb->get_results("SELECT * FROM $table WHERE option_name LIKE '%_rapidsec_cached_%'", ARRAY_A);

    if (!empty($all_results)) {
      return $all_results;
    } else {
      return false;
    }
  }

  public function rapidsec_upgrade_process($upgrader_object, $options)
  {
    if (($options['action'] == 'update' || $options['action'] == 'install') && $options['type'] == 'plugin') {
      $results = $this->get_all_transients();

      if (!empty($results)) {
        foreach ($results as $result) {
          $cache_key = $result['option_name'];
          if (strlen($cache_key) > 80) {
            delete_option($cache_key);
          }
        }
      }
    }
  }

  public function add_action_links(array $actions): array
  {
    $option = is_admin() ? Config::rapidsec_get_option(RAPIDSEC_CONFIG_ADMIN) : Config::rapidsec_get_option(RAPIDSEC_CONFIG_FRONTEND);

    $response = null;
    $plan = null;

    if ($option['token'] != '') {
      $response = $this->api->get_csp($option['token']);
    }

    if (!empty($response) && !isset($response->error)) {
      $plan = $response->currentPlan;
    }

    switch ($plan) {
      case 'free':
        $link_text = 'GO PRO';
        break;
      case 'basic_monthly':
      case 'basic_annual':
      case 'pro_monthly':
      case 'pro_annual':
      case 'premium_monthly':
      case 'premium_annual':
      case 'essentials_monthly':
      case 'essentials_annual':
      case 'teams_monthly':
      case 'teams_annual':
      case 'enterprise_monthly':
      case 'enterprise_annual':
      case 'enterprise':
        if ($response->isCanceled == true) {
          $link_text = 'REACTIVATE';
        } else {
          $link_text = 'PREMIUM';
        }
        break;
      default:
        $link_text = 'VIEW PLAN';
    }

    $links = [
      '<a href="' . admin_url('options-general.php?page=rapidsec') . '">' . __('Settings', 'rapidsec') . '</a>',
      '<a style="color: #5ABE7F; font-weight: bold" target="_blank" href="https://app.rapidsec.com/plans?utm_source=wordpress&utm_medium=agent&utm_campaign=action_link">' . __($link_text, 'rapidsec') . '</a>',
    ];
    $actions = array_merge($actions, $links);

    return $actions;
  }

  function rapidsec_plugin_deactivate_callback()
  {
    $form_data = $_POST['form_data'] ?? '';

    if (!isset($form_data)) {
      wp_send_json_error();
      wp_die();
    }
    $reason = $form_data[0]['value'];

    $configAdmin = Config::rapidsec_get_option(RAPIDSEC_CONFIG_ADMIN);
    $configFrontend = Config::rapidsec_get_option(RAPIDSEC_CONFIG_FRONTEND);
    $configCheckout = Config::rapidsec_get_option(RAPIDSEC_CONFIG_CHECKOUT);
    $tokens = implode(',', [$configAdmin['token'], $configFrontend['token'], $configCheckout['token']]);

    $response = $this->api->rapidsec_send_feedback($reason, $tokens);

    if (!empty($response)) {
      wp_send_json_success();
    }

    wp_die();
  }

  function rapidsec_banner_time_callback()
  {
    $days = isset($_POST['days']) ? sanitize_text_field($_POST['days']) : -1;

    if (!empty($days)) {
      Config::rapidsec_update_option(RAPIDSEC_BANNER_NOTICE_HIDE, true);

      wp_send_json_success(['status' => 200]);
    } else {
      wp_send_json_error();
    }
    wp_die();
  }

  function rapidsec_test_token_callback()
  {
    $body = $this->api->get_csp(sanitize_text_field($_POST['token']));

    if (isset($body->reportCspVersion)) {
      wp_send_json_success([
        'version' => esc_html($body->reportCspVersion),
      ]);
    } else {
      wp_send_json_error();
    }

    wp_die(); // this is required to terminate immediately and return a proper response
  }

  function run_clear_cache()
  {
    $all_transients = $this->get_all_transients();

    if (!empty($all_transients)) {
      foreach ($all_transients as $transient) {
        $option_name = $transient['option_name'];
        if (!empty($option_name)) {
          Config::rapidsec_delete_option($option_name);
        }
      }
    }

    $this->pluginAdditions->apply_clear_cache();
    $this->connector->clear();
  }

  function rapidsec_clear_cache_callback()
  {
    $this->run_clear_cache();

    set_transient(RAPIDSEC_CACHE_ADMIN_NOTICE, true, 3);
    wp_send_json_success(['status' => 200]);

    wp_die();
  }
}
