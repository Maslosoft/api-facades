<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Models;

/**
 * Represents a single OpenAPI operation (verb-specific) within a resource.
 */
final class Op
{
	/**
	 * Primary tag for this operation.
	 */
	public string $tag;

	/**
	 * @var string[]
	 */
	public array $tags;

	/**
	 * Raw path for the operation.
	 */
	public string $path;

	/**
	 * HTTP method in uppercase form (e.g. GET/POST).
	 */
	public string $http;

	/**
	 * Operation identifier from the OpenAPI document.
	 */
	public string $operationId;

	/**
	 * camelCased variant of the operationId used by generators.
	 */
	public string $janeMethod;

	/**
	 * PHPDoc return type for the operation (e.g. \NS\Model\Foo|array<int,\NS\Model\Bar>|mixed).
	 */
	public string $returnDoc;
}
