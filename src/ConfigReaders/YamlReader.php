<?php

namespace Maslosoft\ApiFacades\ConfigReaders;

use Maslosoft\ApiFacades\Interfaces\ConfigReader;
use Symfony\Component\Yaml\Yaml;

class YamlReader implements ConfigReader
{
	public function canRead(string $path): bool
	{
		return str_ends_with($path, '.yaml') || str_ends_with($path, '.yml');
	}

	public function read(string $path): array
	{
		return Yaml::parseFile($path);
	}

}
