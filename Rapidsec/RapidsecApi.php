<?php

namespace Rapidsec;

class HeadersArrayDTO
{
  /**
   * @var string
   */
  public $value;
  /**
   * @var string
   */
  public $name;

  public function __construct($name, $value)
  {
    $this->value = $value;
    $this->name = $name;
  }
}

class ConfigRemoteDTO
{
  /**
   * @var string;
   */
  public $siteId;
  /**
   * @var string;
   */
  public $stageId;
  /**
   * @var boolean;
   */
  public $isCanceled;
  /**
   * @var string;
   */
  public $currentPlan;
  /**
   * @var number;
   */
  public $enforceCspVersion;
  /**
   * @var number;
   */
  public $reportCspVersion;
  /**
   * @var HeadersArrayDTO[];
   */
  public $headers;
  /**
   * @var string
   */
  public $version;

  public function __construct($variable)
  {
    $this->siteId = $variable->siteId;
    $this->stageId = $variable->stageId;
    $this->isCanceled = $variable->isCanceled;
    $this->currentPlan = $variable->currentPlan;
    $this->enforceCspVersion = $variable->enforceCspVersion;
    $this->reportCspVersion = $variable->reportCspVersion;
    $this->headers = array_map(function ($header): HeadersArrayDTO {
      return new HeadersArrayDTO($header->name, $header->value);
    }, $variable->headers);
  }
}

class RapidsecApi
{
  /**
   * @var string
   */
  private $version;

  public function __construct(string $version)
  {
    $this->version = $version;
  }

  function rapidsec_send_report(string $token, string $message): bool
  {
    $version = sprintf('rapidsec_agent-wordpress_%s', $this->version);

    $data_array = [
      'token' => $token,
      'message' => $message,
      'version' => $version,
    ];

    $make_call = $this->call_api('POST', 'https://pipe.rapidsec.net/wordpress-pipeline', json_encode($data_array));
    $response = json_decode($make_call, true);
    if (!empty($response)) {
      return true;
    }
    return false;
  }

  /**
   * @param string #reason
   * @param string #tokens
   *
   */
  function rapidsec_send_feedback(string $reason, string $tokens)
  {
    $api_array = [
      'message' => $reason,
      'pluginType' => 'agent-wordpress',
      'version' => $this->version,
      'tokens' => $tokens,
    ];

    $API_Call = $this->call_api('POST', 'https://pipe.rapidsec.net/plugin/feedback', json_encode($api_array));

    $response = json_decode($API_Call, true);

    return $response;
  }

  /**
   * @param string $token RapidSec token
   *
   * @return ConfigRemoteDTO
   */
  function get_csp(string $token): ConfigRemoteDTO
  {
    $versionStr = sprintf('rapidsec_agent-wordpress_%s', $this->version);

    $url = sprintf(
      'https://api.rapidsec.com/v1/csp/?%s',
      http_build_query([
        'token' => $token,
      ])
    );

    $response = wp_remote_get($url, [
      'timeout' => 10,
      'headers' => [
        'user-agent' => $versionStr,
      ],
    ]);

    return new ConfigRemoteDTO(json_decode(wp_remote_retrieve_body($response)));
  }

  /**
   * @param string $method POST
   * @param string $url API URL
   * @param string $data API data
   *
   * @return mixed
   */
  public function call_api(string $method, string $url, string $data)
  {
    $curl = curl_init();
    switch ($method) {
      case 'POST':
        curl_setopt($curl, CURLOPT_POST, 1);
        if ($data) {
          curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        break;
      default:
        if ($data) {
          $url = sprintf('%s?%s', $url, http_build_query($data));
        }
    }
    // OPTIONS:
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    // EXECUTE:
    $result = curl_exec($curl);
    if (!$result) {
      die('Connection Failure');
    }
    curl_close($curl);

    return $result;
  }
}
