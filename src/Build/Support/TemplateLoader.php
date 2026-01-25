<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Build\Support;

/**
 * Loads template files and extracts PHP code blocks from markdown.
 */
final class TemplateLoader
{
	/**
	 * @var string
	 */
	private string $basePath;

	/**
	 * @param string $basePath Absolute template directory path.
	 */
	public function __construct(string $basePath)
	{
		$this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
	}

	/**
	 * Load a template file and extract fenced PHP block if present.
	 *
	 * @param string $templateName
	 * @return string
	 */
	public function load(string $templateName): string
	{
		$path = $this->basePath . DIRECTORY_SEPARATOR . $templateName;
		$contents = file_get_contents($path);
		if ($contents === false)
		{
			throw new \RuntimeException("Unable to read template '{$path}'.");
		}
		if (preg_match('/```php\\s*(.*?)\\s*```/s', $contents, $matches) === 1)
		{
			return trim($matches[1]);
		}
		return trim($contents);
	}
}
