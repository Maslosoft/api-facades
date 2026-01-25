<?php

namespace Maslosoft\ApiFacades;

use Maslosoft\ApiFacades\Config\GenerateConfig;
use Maslosoft\ApiFacades\Config\InputConfig;
use Maslosoft\ApiFacades\Config\JaneConfig;
use Maslosoft\ApiFacades\Config\OutputConfig;
use Maslosoft\ApiFacades\ConfigReaders\PhpReader;
use Maslosoft\ApiFacades\ConfigReaders\YamlReader;
use Maslosoft\ApiFacades\Exceptions\ConfigurationException;
use Maslosoft\ApiFacades\Interfaces\ConfigReader;

class Config
{
	/**
	 * Loaded config filename with path
	 * @var string
	 */
	public string $filename;

	/**
	 * Loaded config directory
	 * @var string
	 */
	public string $path;

	public JaneConfig $jane;

	public InputConfig $input;

	public OutputConfig $output;

	public GenerateConfig $generate;

	/** @var array<string,mixed> */
	public array $raw;

	protected function __construct(array $cfg, string $path)
	{
		$this->filename = $path;
		$this->path = dirname($path);
		$this->raw = $cfg;
		$this->jane = new JaneConfig($this, (array)($cfg['jane'] ?? []));
		$this->input = new InputConfig($this, (array)($cfg['input'] ?? []));
		$this->output = new OutputConfig($this, (array)($cfg['output'] ?? []));
		$this->generate = new GenerateConfig($this, (array)($cfg['generate'] ?? []));

		if ($this->input->location === '')
		{
			throw new ConfigurationException('Input location is required.');
		}
		if ($this->output->namespace === '')
		{
			throw new ConfigurationException('Output namespace is required.');
		}
	}

	/**
	 * Load config from path
	 * @param string $path
	 * @return static
	 */
	public static function load(string $path): static
	{
		if (!file_exists($path))
		{
			throw new ConfigurationException("Configuration file '{$path}' does not exist.");
		}
		if (is_dir($path))
		{
			throw new ConfigurationException("Configuration file '{$path}' is a directory.");
		}
		if (!is_readable($path))
		{
			throw new ConfigurationException("Configuration file '{$path}' is not readable.");
		}
		$cfg = [];
		$couldRead = false;
		foreach (static::getReaders() as $reader)
		{
			if ($reader->canRead($path))
			{
				$couldRead = true;
				$cfg = $reader->read($path);
				break;
			}
		}
		if (!$couldRead)
		{
			throw new ConfigurationException("Configuration file type for '{$path}' is not supported.");
		}
		if (empty($cfg))
		{
			throw new ConfigurationException("Configuration from file '{$path}' is empty.");
		}
		return self::create($cfg, $path);
	}

	/**
	 * Create configuration from provided array
	 * @param array  $cfg
	 * @param string $path
	 * @return static
	 */
	private static function create(array $cfg, string $path): static
	{
		return new static($cfg, $path);
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
