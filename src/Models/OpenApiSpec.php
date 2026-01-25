<?php

namespace Maslosoft\ApiFacades\Models;

/**
 * Aggregated OpenAPI document representation for generators.
 *
 * This class contains the raw document payload together with structured
 * resources and models to simplify downstream processing.
 */
final class OpenApiSpec
{
	/**
	 * Raw OpenAPI document decoded into an array.
	 *
	 * @var array<string, mixed>
	 */
	public array $raw;

	/**
	 * Resources indexed by their path.
	 *
	 * @var array<string, Resource>
	 */
	public array $resources;

	/**
	 * Models indexed by schema name.
	 *
	 * @var array<string, Model>
	 */
	public array $models;

	/**
	 * @param array<string, mixed> $raw
	 * @param array<string, Resource> $resources
	 * @param array<string, Model> $models
	 */
	public function __construct(array $raw, array $resources, array $models)
	{
		$this->raw = $raw;
		$this->resources = $resources;
		$this->models = $models;
	}
}
