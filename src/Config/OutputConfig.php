<?php

namespace Maslosoft\ApiFacades\Config;

/**
 * Output destination configuration.
 */
class OutputConfig
{
	/**
	 * Namespace for generated facades.
	 *
	 * @var string
	 */
	public string $namespace;

	/**
	 * Discover output directory based on PSR-4 composer autoload.
	 *
	 * @var bool
	 */
	public bool $discoverOutput;

	/**
	 * Directory to write output. Empty means "use discovery".
	 *
	 * @var string
	 */
	public string $output;

	/**
	 * @param array<string,mixed> $data
	 */
	public function __construct(array $data = [])
	{
		$this->namespace = (string)($data['namespace'] ?? '');
		$this->discoverOutput = (bool)($data['discoverOutput'] ?? false);
		$this->output = (string)($data['output'] ?? '');
	}
}
