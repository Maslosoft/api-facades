<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Models;

/**
 * Describes a grouped OpenAPI resource identified by a path.
 *
 * Resources are a container for verb-specific operations and the unique
 * tags applied to those operations.
 */
final class Resource
{
	/**
	 * Map of HTTP verb to OpenAPI operation metadata.
	 *
	 * @var array<string, Op>
	 */
	public array $verbs = [];

	/**
	 * Resource name derived from the URL path segments.
	 */
	public string $name;

	/**
	 * Raw path (e.g. "/users/{id}").
	 */
	public string $path;

	/**
	 * Unique tags associated with operations under this path.
	 *
	 * @var string[]
	 */
	public array $tags;

	/**
	 * @param string   $name method name, e.g. 'run' or 'profile'
	 * @param string   $path raw path (e.g. "/users/{id}")
	 * @param string[] $tags unique tags aggregated from operations
	 */
	public function __construct(
		string $name,
		string $path,
		array $tags
	)
	{
		$this->tags = $tags;
		$this->path = $path;
		$this->name = $name;
	}
}
