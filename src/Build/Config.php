<?php

namespace Maslosoft\ApiFacades\Build;

use Maslosoft\ApiFacades\Config\InputConfig;
use Maslosoft\ApiFacades\Config\JaneConfig;
use Maslosoft\ApiFacades\Config\OutputConfig;
use Maslosoft\ApiFacades\ConfigReaders\PhpReader;
use Maslosoft\ApiFacades\ConfigReaders\YamlReader;
use Maslosoft\ApiFacades\Exceptions\ConfigurationException;
use Maslosoft\ApiFacades\Interfaces\ConfigReader;

class Config
{
	public JaneConfig $jane;
	public InputConfig $input;
	public OutputConfig $output;
	/** @var array<string,mixed> */
	public array $generate;
	/** @var array<string,mixed> */
	public array $raw;

	protected function __construct(array $cfg)
	{
		$this->raw = $cfg;
		$this->jane = new JaneConfig((array)($cfg['jane'] ?? []));
		$this->input = new InputConfig((array)($cfg['input'] ?? []));
		$this->output = new OutputConfig((array)($cfg['output'] ?? []));
		$this->generate = (array)($cfg['generate'] ?? []);

		if($this->input->location === '')
		{
			throw new ConfigurationException('Input location is required.');
		}
		if($this->output->namespace === '')
		{
			throw new ConfigurationException('Output namespace is required.');
		}
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
	 * @return array<string,mixed>
	 */
	public function getModuleNamerConfig(): array
	{
		return (array)($this->generate['modules']['namer'] ?? []);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getOperationNamerConfig(): array
	{
		return (array)($this->generate['operations']['namer'] ?? []);
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
