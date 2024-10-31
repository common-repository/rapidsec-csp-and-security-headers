<?php

namespace Rapidsec;

require_once ABSPATH . 'wp-admin/includes/misc.php';

class ApacheConnector implements DeploymentMethod
{
  /**
   * @var string[][][]
   */
  private $typeRules;

  public function __construct()
  {
    $this->typeRules = [
      'frontend' => [['    SetEnvIfExpr "env(\'RS_APPLIED\') != 1" RS_HIT_frontend_apply RS_APPLIED'], ['']],
      'admin' => [['    SetEnvIf Request_URI "/wp-admin" RS_HIT_admin=1', '    SetEnvIfExpr "env(\'RS_APPLIED\') != 1 && env(\'RS_HIT_admin\') == 1" RS_HIT_admin_apply RS_APPLIED'], ['']],
    ];
  }

  public function apply_on_request(array $cspHeaders, string $token): ?string
  {
    return null;
  }

  public function apply_on_new_config(ConfigRemoteDTO $cspConfig, string $token, string $type): ?string
  {
    $adminToken = ['admin', Config::rapidsec_get_option(RAPIDSEC_CONFIG_ADMIN)['token']];
    $checkoutToken = ['checkout', Config::rapidsec_get_option(RAPIDSEC_CONFIG_CHECKOUT)['token']];
    $frontendToken = ['frontend', Config::rapidsec_get_option(RAPIDSEC_CONFIG_FRONTEND)['token']];

    $configs = array_reduce(
      [$adminToken, $frontendToken, $checkoutToken],
      function ($carry, $config) {
        [$type, $token] = $config;
        if (empty($config[1])) {
          return $carry;
        }
        $cachedConfig = get_transient(sprintf('rapidsec_cached_%s', md5($token)));
        if (empty($cachedConfig)) {
          return $carry;
        }

        return array_merge($carry, [[$type, $cachedConfig]]);
      },
      []
    );

    return $this->update_headers_directives($configs);
  }

  /**
   * @param array $configs
   *
   * @return string|null
   */
  private function update_headers_directives(array $configs): ?string
  {
    $lines = $this->apache_headers_directives($configs);
    $result = insert_with_markers($this->get_htaccess_filename(), 'RapidsecHttpHeaders', $lines);

    if ($result != true) {
      return __('Cannot modify header information - errors inserting rules', 'rapidsec');
    }

    return null;
  }

  private function get_config_headers(array $carry, array $config): array
  {
    [$type, $cspConfig] = $config;
    $lines = [];

    $routeRules = $this->typeRules[$type] ?? [[''], ['']];

    foreach ((array) $cspConfig->headers as $header) {
      $lines[] = sprintf('    Header set %s %s %s', $header->name, sprintf('%1$s%2$s%1$s', strpos($header->value, '"') === false ? '"' : "'", $header->value), 'env=RS_HIT_' . $type . '_apply');
    }

    return array_merge($carry, $routeRules[0], $lines, $routeRules[1]);
  }

  private function apache_headers_directives(array $cspHeaders): array
  {
    $configLines = array_reduce(
      $cspHeaders,
      function ($carry, $config) {
        return $this->get_config_headers($carry, $config);
      },
      []
    );

    return array_merge(['<IfModule mod_headers.c>'], $configLines, ['</IfModule>']);
  }

  private function get_htaccess_filename(): string
  {
    return ABSPATH . '.htaccess';
  }

  public function clear(): ?string
  {
    insert_with_markers($this->get_htaccess_filename(), 'RapidsecHttpHeaders', ['']);

    return null;
  }
}
