<?php

namespace Maslosoft\ApiFacades\Support;

use Maslosoft\ApiFacades\Config;
use Maslosoft\ApiFacades\Interfaces\ConfigAware;
use Maslosoft\ApiFacades\Traits\ConfigAwareTrait;

class PathResolver implements ConfigAware
{
	use ConfigAwareTrait;

	public function __construct(Config $config)
	{
		$this->config = $config;
	}

	/**
	 * Resolve path, depending on if it's URL, absolute or relative.
	 * 1. If starts with http, leave as-is - its URL
	 * 2. If starts with /, leave as-is - absolute URL
	 * 3. Otherways, resolve path based on $config->path
	 *
	 * @param string $path
	 * @return string
	 */
	public function resolve(string $path): string
	{
		if ($path === '')
		{
			return $path;
		}

		$lowerPath = strtolower($path);
		if (str_starts_with($lowerPath, 'http://') || str_starts_with($lowerPath, 'https://'))
		{
			return $path;
		}
		if (str_starts_with($path, '/'))
		{
			return $path;
		}
		if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path))
		{
			return $path;
		}

		// Trim dot-prefixed local path dot
		if(str_starts_with($path, './') || str_starts_with($path, '.\\'))
		{
			$path = ltrim($path, '.');
		}

		$basePath = rtrim($this->config->path, DIRECTORY_SEPARATOR);
		$relativePath = ltrim($path, DIRECTORY_SEPARATOR);

		return $basePath . DIRECTORY_SEPARATOR . $relativePath;
	}
}
