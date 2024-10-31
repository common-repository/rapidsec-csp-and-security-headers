<?php

namespace Rapidsec;

use function __;

class PhpConnector implements DeploymentMethod
{
  public function apply_on_new_config(ConfigRemoteDTO $cspConfig, string $token, string $type): ?string
  {
    return null;
    // TODO: Implement apply_on_new_config() method.
  }

  public function apply_on_request(array $cspHeaders, string $token): ?string
  {
    if (empty($cspHeaders)) {
      return null;
    }

    if (headers_sent() == true) {
      return __('Cannot modify header information - headers already sent', 'rapidsec');
    }

    foreach ((array) $cspHeaders as $header) {
      header(sprintf('%s: %s', $header->name, $header->value));
    }
    return null;
  }

  public function clear(): ?string
  {
    return null;
    // TODO: Implement clear() method.
  }
}
