<?php

namespace Maslosoft\ApiFacades\Interfaces;

interface ConfigReader
{
	public function canRead(string $path): bool;

	public function read(string $path): array;
}
