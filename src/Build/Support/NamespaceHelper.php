<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Build\Support;

/**
 * Namespace manipulation helpers for generated classes.
 */
final class NamespaceHelper
{
	/**
	 * Extract short class name from FQCN.
	 *
	 * @param string $fqcn
	 * @return string
	 */
	public function shortClassName(string $fqcn): string
	{
		$trimmed = trim($fqcn, '\\');
		$pos = strrpos($trimmed, '\\');
		return $pos === false ? $trimmed : substr($trimmed, $pos + 1);
	}

	/**
	 * Extract namespace from FQCN.
	 *
	 * @param string $fqcn
	 * @return string
	 */
	public function namespaceFromFqcn(string $fqcn): string
	{
		$trimmed = trim($fqcn, '\\');
		$pos = strrpos($trimmed, '\\');
		return $pos === false ? '' : substr($trimmed, 0, $pos);
	}

	/**
	 * Resolve namespace relative to base namespace.
	 *
	 * @param string $namespace
	 * @param string $baseNamespace
	 * @return string
	 */
	public function relativeNamespace(string $namespace, string $baseNamespace): string
	{
		$normalizedBase = trim($baseNamespace, '\\');
		$normalized = trim($namespace, '\\');
		if ($normalizedBase === '')
		{
			return $normalized;
		}
		if ($normalized === $normalizedBase)
		{
			return '';
		}
		$prefix = $normalizedBase . '\\';
		if (str_starts_with($normalized, $prefix))
		{
			return substr($normalized, strlen($prefix));
		}
		return $normalized;
	}
}
