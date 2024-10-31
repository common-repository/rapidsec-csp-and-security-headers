<?php
/**
 * The plugin settings file
 *
 * @package Rapidsec
 */

declare(strict_types=1);

namespace Rapidsec;

interface AdditionalFunctionalityPerPlugin
{
  /**
   * @return array|bool
   */
  public function get_custom_token();

  public function get_custom_option(): array;

  public function clear_cache();
}

class WooCommerce implements AdditionalFunctionalityPerPlugin
{
  public $isActive;

  public function __construct()
  {
    $hasPlugin = in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));

    $config = Config::rapidsec_get_option(RAPIDSEC_CONNECTOR_CONFIG);

    $type = $config['connection_type'] ?? 'htaccess';
    $this->isActive = $hasPlugin && $type === 'php';
  }

  public function get_custom_token()
  {
    if (!$this->isActive) {
      return false;
    }

    // Function's exist from woocommerce plugin
    $isMainWoocommercePage = is_checkout() || is_shop() || is_product_category() || is_product() || is_cart() || is_account_page();
    if (!$isMainWoocommercePage) {
      return false;
    }

    return Config::rapidsec_get_option(RAPIDSEC_CONFIG_CHECKOUT);
  }

  public function get_custom_option(): array
  {
    if (!$this->isActive) {
      return [];
    }

    $option = Config::rapidsec_get_option(RAPIDSEC_CONFIG_CHECKOUT);

    return [
      'checkout' => array_merge(!empty($option) ? $option : ['token' => '', 'polling_interval' => 15], [
        'title' => 'Checkout Policy',
        'description' => 'Set the policy to be used in the Woocommerce checkout pages.',
        'missing_token_error' => 'Checkout Policy API Token is empty',
      ]),
    ];
  }

  public function clear_cache()
  {
    // TODO: Implement clear_cache() method.
  }
}

class WpRocket implements AdditionalFunctionalityPerPlugin
{
  public function get_custom_option(): array
  {
    return [];
  }

  public function get_custom_token(): bool
  {
    return false;
  }

  public function clear_cache()
  {
    // Clear the cache
    if (function_exists('rocket_clean_domain')) {
      rocket_clean_domain();
    }
  }
}

class PluginAdditions
{
  /**
   * @var array<AdditionalFunctionalityPerPlugin >
   */
  public $pluginAdditional;

  /**
   * @param string $version
   */
  public function __construct(string $version)
  {
    $this->pluginAdditional = [
      'wooCommerce' => new WooCommerce(),
      'WpRocket' => new WpRocket(),
    ];
  }

  /**
   * @return array|false
   */
  public function get_custom_token()
  {
    foreach ($this->pluginAdditional as $key => $plugin) {
      $option = $plugin->get_custom_token();
      if ($option === false) {
        continue;
      }

      return [$key, $option];
    }

    return false;
  }

  public function apply_custom_options(array $options): array
  {
    foreach ($this->pluginAdditional as $plugin) {
      $options = array_merge($options, $plugin->get_custom_option());
    }

    return $options;
  }

  public function apply_clear_cache()
  {
    foreach ($this->pluginAdditional as $plugin) {
      $plugin->clear_cache();
    }
  }
}
