<?php

namespace Maslosoft\ApiFacades\Config;

use Maslosoft\ApiFacades\Config;
use Maslosoft\ApiFacades\Interfaces\ConfigAware;
use Maslosoft\ApiFacades\Support\ComposerDiscover;
use Maslosoft\ApiFacades\Support\PathResolver;
use Maslosoft\ApiFacades\Traits\ConfigAwareTrait;

/**
 * Output destination configuration.
 */
class OutputConfig implements ConfigAware
{
	use ConfigAwareTrait;

	public const string DefaultClass = 'Client';

	/**
	 * Namespace for generated facades.
	 *
	 * @var string
	 */
	public string $namespace;

	/**
	 * Root API class name, relative to namespace
	 * @var string
	 */
	public string $class = self::DefaultClass;

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
	public string $path;

	/**
	 * @param Config              $config
	 * @param array<string,mixed> $data
	 */
	public function __construct(Config $config, array $data = [])
	{
		$this->namespace = (string)($data['namespace'] ?? '');
		$this->class = (string)($data['class'] ?? '');
		$this->discoverOutput = (bool)($data['discoverOutput'] ?? false);
		if($this->discoverOutput)
		{
			$this->path = (new ComposerDiscover($config))->discover($this->namespace);
		}
		else
		{
			$this->path = (new PathResolver($config))->resolve((string)($data['path'] ?? ''));
		}
	}
}
