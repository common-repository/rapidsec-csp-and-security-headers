<?php

namespace Rapidsec;

require_once 'connectors/PhpConnector.php';
require_once 'connectors/ApacheConnector.php';

interface DeploymentMethod
{
  /**
   * @param $cspHeaders string[]
   * @param string $token
   *
   * @return null|string
   */
  public function apply_on_request(array $cspHeaders, string $token): ?string;

  /**
   * @param $cspConfig ConfigRemoteDTO
   * @param string $token
   * @param string $type
   *
   * @return null|string
   */
  public function apply_on_new_config(ConfigRemoteDTO $cspConfig, string $token, string $type): ?string;

  /**
   * @return null|string
   */
  public function clear(): ?string;
}

class Connector implements DeploymentMethod
{
  /**
   * @var array<DeploymentMethod >
   */
  private $connectors;

  /**
   * @param string $version
   */
  public function __construct(string $version)
  {
    $this->connectors = [
      'php' => new PhpConnector(),
      'htaccess' => new ApacheConnector(),
    ];
  }

  public function apply_on_request(array $cspHeaders, string $token): ?string
  {
    $connector = $this->get_connector();

    return $connector->apply_on_request($cspHeaders, $token);
  }

  public function apply_on_new_config(ConfigRemoteDTO $cspConfig, string $token, string $type): ?string
  {
    $connector = $this->get_connector();

    return $connector->apply_on_new_config($cspConfig, $token, $type);
  }

  public function clear(): ?string
  {
    $result = '';
    foreach ($this->connectors as $connector) {
      $connectorResult = $connector->clear();
      if (!empty($connectorResult)) {
        $result = $connectorResult;
      }
    }

    return $result;
  }

  /**
   * @return DeploymentMethod
   */
  private function get_connector(): DeploymentMethod
  {
    $config = Config::rapidsec_get_option(RAPIDSEC_CONNECTOR_CONFIG);

    $type = $config['connection_type'] ?? 'php';
    $connector = $this->connectors[$type];

    if (!empty($connector)) {
      return $connector;
    }

    return $this->connectors['php'];
  }
}
