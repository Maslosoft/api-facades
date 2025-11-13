<?php

namespace Maslosoft\ApiFacades\ConfigReaders;

use Maslosoft\ApiFacades\Interfaces\ConfigReader;

class PhpReader implements ConfigReader
{
	public function canRead(string $path): bool
	{
		return str_ends_with($path, '.php');
	}

	public function read(string $path): array
	{
		return require $path;
	}

}
