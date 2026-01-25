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
	 * Location of OpenAPI spec, either as URL or as file.
	 *
	 * Paths are resolved against config file location if not absolute.
	 *
	 * Examples:
	 *
	 * 1. http://127.0.0.1:8000/openapi.json
	 * 2. openapi.json
	 * 3. /var/www/acme/openapi.json (not really recommended)
	 *
	 * @var string
	 */
	public string $location;

	/**
	 * @param Config              $config
	 * @param array<string,mixed> $data
	 */
	public function __construct(Config $config, array $data = [])
	{
		$this->config = $config;
		$this->location = (new PathResolver($config))->resolve((string)($data['location'] ?? ''));
	}
}
