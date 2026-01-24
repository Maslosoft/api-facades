<?php

namespace Maslosoft\ApiFacades\Config;

use Maslosoft\ApiFacades\Config;
use Maslosoft\ApiFacades\Interfaces\ConfigAware;
use Maslosoft\ApiFacades\Support\PathResolver;
use Maslosoft\ApiFacades\Traits\ConfigAwareTrait;

/**
 * Input source configuration.
 */
class InputConfig implements ConfigAware
{
	use ConfigAwareTrait;

	/**
	 * Location of OpenAPI spec or other input.
	 *
	 * Example: http://127.0.0.1:8000/openapi.json
	 *
	 * @var string
	 */
	public string $location;

	/**
	 * @param array<string,mixed> $data
	 */
	public function __construct(Config $config, array $data = [])
	{
		$this->config = $config;
		$this->location = (new PathResolver($config))->resolve((string)($data['location'] ?? ''));
	}
}
