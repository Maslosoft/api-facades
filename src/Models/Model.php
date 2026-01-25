<?php

namespace Maslosoft\ApiFacades\Models;

/**
 * Describes a schema model discovered in the OpenAPI document.
 *
 * This representation keeps the schema payload intact while also exposing
 * the most common, convenient fields for generators.
 */
final class Model
{
	/**
	 * Schema name as defined in components/schemas.
	 */
	public string $name;

	/**
	 * Display title of the schema, if provided.
	 */
	public string $title;

	/**
	 * Optional verbose description of the model.
	 */
	public ?string $description;

	/**
	 * JSON schema type, usually "object", "array", "string", etc.
	 */
	public string $type;

	/**
	 * Raw schema payload for the model.
	 *
	 * @var array<string, mixed>
	 */
	public array $schema;

	/**
	 * Properties defined on the schema (for object models).
	 *
	 * @var array<string, mixed>
	 */
	public array $properties;

	/**
	 * Required property names.
	 *
	 * @var array<int, string>
	 */
	public array $required;

	/**
	 * Enum values, if the schema is an enum.
	 *
	 * @var array<int, string|int|float|bool>
	 */
	public array $enum;

	/**
	 * @param array<string, mixed> $schema
	 */
	public function __construct(string $name, array $schema)
	{
		$this->name = $name;
		$this->schema = $schema;
		$this->title = (string)($schema['title'] ?? $name);
		$this->description = isset($schema['description']) ? (string)$schema['description'] : null;
		$this->type = (string)($schema['type'] ?? 'object');
		$this->properties = (array)($schema['properties'] ?? []);
		$this->required = array_values((array)($schema['required'] ?? []));
		$this->enum = array_values((array)($schema['enum'] ?? []));
	}
}
