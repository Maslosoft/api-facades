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

		$basePath = rtrim($this->config->path, DIRECTORY_SEPARATOR);
		$relativePath = ltrim($path, DIRECTORY_SEPARATOR);

		return $basePath . DIRECTORY_SEPARATOR . $relativePath;
	}
}
