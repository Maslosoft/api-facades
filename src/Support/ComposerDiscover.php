<?php

namespace Maslosoft\ApiFacades\Support;

use JsonException;
use Maslosoft\ApiFacades\Config;
use Maslosoft\ApiFacades\Exceptions\ConfigurationException;
use Maslosoft\ApiFacades\Interfaces\ConfigAware;
use Maslosoft\ApiFacades\Traits\ConfigAwareTrait;
use Maslosoft\Cli\Shared\Io;

class ComposerDiscover implements ConfigAware
{
	use ConfigAwareTrait;
	public function __construct(Config $config)
	{
		$this->config = $config;
	}

	/**
	 * Discover project namespace folder location based on PSR and namespace
	 * For example if composer contains PSR-4:
	 *
	 * ```json
	 *     "autoload": {
	 *        "psr-4": {
	 *            "Acme\\Project\\": "src/"
	 *        },
	 * ```
	 *
	 * And API namespace is `Acme\Project\Api`, the path should point to:
	 *
	 * ```bash
	 * /project-root/src/Api
	 * ```
	 * If namespace of API does not match any autoload, it will throw exception
	 *
	 * @throws ConfigurationException
	 * @param string $namespace
	 * @return string
	 */
	public function discover(string $namespace): string
	{
		$composerPath = $this->findComposerJson();
		$composer = $this->readComposerJson($composerPath);
		$autoload = (array)($composer['autoload']['psr-4'] ?? []);
		if ($autoload === [])
		{
			throw new ConfigurationException('Composer autoload psr-4 configuration is empty.');
		}

		$namespace = trim($namespace, '\\');
		$namespaceWithSlash = $namespace . '\\';

		$bestMatch = null;
		foreach ($autoload as $prefix => $paths)
		{
			$normalizedPrefix = trim((string)$prefix, '\\') . '\\';
			if (!str_starts_with($namespaceWithSlash, $normalizedPrefix))
			{
				continue;
			}
			$length = strlen($normalizedPrefix);
			if ($bestMatch === null || $length > $bestMatch['length'])
			{
				$bestMatch = [
					'length' => $length,
					'prefix' => $normalizedPrefix,
					'paths' => $paths,
				];
			}
		}

		if ($bestMatch === null)
		{
			throw new ConfigurationException("Namespace '{$namespace}' does not match any PSR-4 autoload prefix.");
		}

		$relative = substr($namespaceWithSlash, $bestMatch['length']);
		$relative = trim($relative, '\\');

		$paths = is_array($bestMatch['paths']) ? $bestMatch['paths'] : [$bestMatch['paths']];
		if ($paths === [])
		{
			throw new ConfigurationException('Composer autoload psr-4 configuration has no paths.');
		}

		$basePath = $this->normalizePath((string)$paths[0]);
		$composerDir = dirname($composerPath);

		$segments = [$composerDir, $basePath];
		if ($relative !== '')
		{
			$segments[] = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
		}

		return $this->joinPaths($segments);
	}

	private function findComposerJson(): string
	{
		$path = $this->config->path;
		if (empty($path))
		{
			throw new ConfigurationException('Unable to determine current working directory.');
		}

		if(!Io::dirExists($path))
		{
			throw new ConfigurationException('Working directory does not exist.');
		}

		while (true)
		{
			$candidate = $path . DIRECTORY_SEPARATOR . 'composer.json';
			if (is_file($candidate))
			{
				return $candidate;
			}
			$parent = dirname($path);
			if ($parent === $path)
			{
				break;
			}
			$path = $parent;
		}

		throw new ConfigurationException('composer.json not found while discovering output path.');
	}

	/**
	 * @return array<string,mixed>
	 */
	private function readComposerJson(string $composerPath): array
	{
		$contents = file_get_contents($composerPath);
		if ($contents === false)
		{
			throw new ConfigurationException("Unable to read composer.json at '{$composerPath}'.");
		}

		try
		{
			return (array)json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $exception)
		{
			throw new ConfigurationException("Invalid composer.json at '{$composerPath}': {$exception->getMessage()}");
		}
	}

	private function normalizePath(string $path): string
	{
		return trim($path, "/\\");
	}

	/**
	 * @param string[] $segments
	 */
	private function joinPaths(array $segments): string
	{
		if ($segments === [])
		{
			return '';
		}

		$clean = [];
		$first = array_shift($segments);
		$clean[] = rtrim($first, "/\\");
		foreach ($segments as $segment)
		{
			$clean[] = trim($segment, "/\\");
		}

		return implode(DIRECTORY_SEPARATOR, $clean);
	}
}
