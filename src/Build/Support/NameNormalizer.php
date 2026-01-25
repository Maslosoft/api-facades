<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Build\Support;

/**
 * Normalizes identifiers used for generated class and method names.
 */
final class NameNormalizer
{
	/**
	 * Normalize a module class name segment into StudlyCase.
	 *
	 * @param string $name
	 * @return string
	 */
	public function className(string $name): string
	{
		$cleaned = preg_replace('/[^a-zA-Z0-9]+/', ' ', $name);
		$cleaned = $cleaned === null ? '' : trim($cleaned);
		if ($cleaned === '')
		{
			return 'Module';
		}
		$studly = str_replace(' ', '', ucwords(strtolower($cleaned)));
		if ($studly === '')
		{
			return 'Module';
		}
		if (is_numeric($studly[0]))
		{
			return 'Module' . $studly;
		}
		return $studly;
	}

	/**
	 * Normalize a method name into camelCase.
	 *
	 * @param string $name
	 * @return string
	 */
	public function methodName(string $name): string
	{
		$cleaned = preg_replace('/[^a-zA-Z0-9]+/', ' ', $name);
		$cleaned = $cleaned === null ? '' : trim($cleaned);
		if ($cleaned === '')
		{
			return '';
		}
		$camel = str_replace(' ', '', ucwords(strtolower($cleaned)));
		if ($camel === '')
		{
			return '';
		}
		$camel = lcfirst($camel);
		if (is_numeric($camel[0]))
		{
			return 'operation' . ucfirst($camel);
		}
		return $camel;
	}

	/**
	 * Convert an accessor (camelCase) into a class name (StudlyCase).
	 *
	 * @param string $accessor
	 * @return string
	 */
	public function accessorToClass(string $accessor): string
	{
		if ($accessor === '')
		{
			return 'Module';
		}
		return ucfirst($accessor);
	}

	/**
	 * Normalize a string into a safe accessor name (camelCase).
	 *
	 * @param string $name
	 * @param string $fallback
	 * @return string
	 */
	public function accessorName(string $name, string $fallback = 'module'): string
	{
		$method = $this->methodName($name);
		if ($method !== '')
		{
			return $method;
		}
		$fallbackMethod = $this->methodName($fallback);
		return $fallbackMethod === '' ? 'module' : $fallbackMethod;
	}
}
