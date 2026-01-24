<?php

namespace Maslosoft\ApiFacades\Support;

use Maslosoft\ApiFacades\Build\Config;
use Maslosoft\ApiFacades\Interfaces\ConfigAware;
use Maslosoft\ApiFacades\Traits\ConfigAwareTrait;

class PathResolver implements ConfigAware
{
	use ConfigAwareTrait;

	public function __construct(Config $config)
	{
		$this->config = $config;
	}

	public function resolve(string $path): string
	{
		// TODO:
		// If starts with http, leave as-is - its URL
		// If starts with /, leave as-is - absolute URL
		// Otherways, resolve path based on $config->path
		return $path;
	}
}