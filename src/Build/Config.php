<?php

namespace Maslosoft\ApiFacades\Build;

use Maslosoft\ApiFacades\ConfigReaders\PhpReader;
use Maslosoft\ApiFacades\ConfigReaders\YamlReader;
use Maslosoft\ApiFacades\Exceptions\ConfigurationException;
use Maslosoft\ApiFacades\Interfaces\ConfigReader;

class Config
{
	protected function __construct(array $cfg)
	{
		// TODO: Apply configuration from array
	}

	/**
	 * Load config from path
	 * @param string $path
	 * @return Config
	 */
	public static function load(string $path): Config
	{
		if(!file_exists($path))
		{
			throw new ConfigurationException("Configuration file '{$path}' does not exist.");
		}
		if(is_dir($path))
		{
			throw new ConfigurationException("Configuration file '{$path}' is a directory.");
		}
		if(!is_readable($path))
		{
			throw new ConfigurationException("Configuration file '{$path}' is not readable.");
		}
		$cfg = [];
		foreach(static::getReaders() as $reader)
		{
			if($reader->canRead($path))
			{
				$cfg = $reader->read($path);
				break;
			}
		}
		if(empty($cfg))
		{
			throw new ConfigurationException("Configuration from file '{$path}' is empty.");
		}
		return self::create($cfg);
	}

	/**
	 * Create configuration from provided array
	 * @param array $cfg
	 * @return Config
	 */
	private static function create(array $cfg): Config
	{
		return new static($cfg);
	}

	/**
	 * @return ConfigReader[]
	 */
	public static function getReaders(): array
	{
		// MAYBE: Could be collected with signals
		return [
			new PhpReader,
			new YamlReader,
		];
	}
}
