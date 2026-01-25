<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Support;

use Maslosoft\ApiFacades\Models\Model;
use Maslosoft\ApiFacades\Models\OpenApiSpec;
use Maslosoft\ApiFacades\Models\Op;
use Maslosoft\ApiFacades\Models\Resource;

/**
 * Converts an OpenAPI JSON document into a structured specification model.
 */
class OpenApiReader
{
	/**
	 * Read OpenAPI JSON document and convert it into a structured representation.
	 *
	 * @param string $path Path or URL to the OpenAPI JSON specification.
	 * @return OpenApiSpec Fully materialized OpenAPI specification.
	 */
	public function read(string $path): OpenApiSpec
	{
		$contents = $this->readContents($path);
		$document = $this->decodeDocument($contents, $path);

		$models = $this->buildModels($document);
		$resources = $this->buildResources($document, $models);

		return new OpenApiSpec($document, $resources, $models);
	}

	/**
	 * Load the OpenAPI JSON document from a local path or URL.
	 *
	 * @param string $path
	 * @return string
	 */
	private function readContents(string $path): string
	{
		if (filter_var($path, FILTER_VALIDATE_URL))
		{
			$contents = file_get_contents($path);
			if ($contents === false)
			{
				throw new \RuntimeException("Unable to read OpenAPI document from URL '{$path}'.");
			}
			return $contents;
		}

		if (!file_exists($path))
		{
			throw new \RuntimeException("OpenAPI document '{$path}' does not exist.");
		}
		if (!is_readable($path))
		{
			throw new \RuntimeException("OpenAPI document '{$path}' is not readable.");
		}
		$contents = file_get_contents($path);
		if ($contents === false)
		{
			throw new \RuntimeException("Unable to read OpenAPI document from '{$path}'.");
		}

		return $contents;
	}

	/**
	 * Decode the JSON payload into an array document.
	 *
	 * @param string $contents
	 * @param string $path
	 * @return array<string, mixed>
	 */
	private function decodeDocument(string $contents, string $path): array
	{
		try
		{
			$decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
		}
		catch (\JsonException $exception)
		{
			throw new \RuntimeException(
				"OpenAPI document '{$path}' contains invalid JSON: {$exception->getMessage()}",
				0,
				$exception
			);
		}

		if (!is_array($decoded))
		{
			throw new \RuntimeException("OpenAPI document '{$path}' did not decode to an array.");
		}

		return $decoded;
	}

	/**
	 * Build model definitions from OpenAPI component schemas.
	 *
	 * @param array<string, mixed> $document
	 * @return array<string, Model>
	 */
	private function buildModels(array $document): array
	{
		$schemas = (array)($document['components']['schemas'] ?? []);
		$models = [];

		foreach ($schemas as $name => $schema)
		{
			if (!is_array($schema))
			{
				continue;
			}
			$models[$name] = new Model((string)$name, $schema);
		}

		return $models;
	}

	/**
	 * Build resources for each OpenAPI path and its verb operations.
	 *
	 * @param array<string, mixed> $document
	 * @param array<string, Model> $models
	 * @return array<string, Resource>
	 */
	private function buildResources(array $document, array $models): array
	{
		$paths = (array)($document['paths'] ?? []);
		$resources = [];
		$httpMethods = [
			'get',
			'post',
			'put',
			'delete',
			'patch',
			'head',
			'options',
		];

		foreach ($paths as $path => $pathItem)
		{
			if (!is_array($pathItem))
			{
				continue;
			}

			$resourceName = $this->resourceNameFromPath((string)$path);
			$verbs = [];
			$tags = [];

			foreach ($httpMethods as $method)
			{
				if (!array_key_exists($method, $pathItem))
				{
					continue;
				}
				$operation = $pathItem[$method];
				if (!is_array($operation))
				{
					continue;
				}

				$operationTags = $this->extractTags($operation);
				if ($operationTags === [])
				{
					$operationTags = ['default'];
				}
				$tags = array_merge($tags, $operationTags);

				$op = new Op();
				$op->tag = $this->extractPrimaryTag($operationTags);
				$op->path = (string)$path;
				$op->http = strtoupper($method);
				$op->operationId = (string)($operation['operationId'] ?? '');
				$op->janeMethod = $this->camelizeOperationId($op->operationId);
				$op->returnDoc = $this->buildReturnDoc($operation, $models);

				$verbs[$method] = $op;
			}

			$resourceTags = $this->uniqueTags($tags);
			$resource = new Resource($resourceName, (string)$path, $resourceTags);
			$resource->verbs = $verbs;

			$resources[(string)$path] = $resource;
		}

		return $resources;
	}

	/**
	 * Determine the response return type for a given operation.
	 *
	 * @param array<string, mixed> $operation
	 * @param array<string, Model> $models
	 * @return string
	 */
	private function buildReturnDoc(array $operation, array $models): string
	{
		$responses = (array)($operation['responses'] ?? []);
		if ($responses === [])
		{
			return 'mixed';
		}

		$priority = ['200', '201', '202', 'default'];
		$response = null;
		foreach ($priority as $code)
		{
			if (isset($responses[$code]) && is_array($responses[$code]))
			{
				$response = $responses[$code];
				break;
			}
		}
		if ($response === null)
		{
			foreach ($responses as $candidate)
			{
				if (is_array($candidate))
				{
					$response = $candidate;
					break;
				}
			}
		}
		if ($response === null)
		{
			return 'mixed';
		}

		$content = (array)($response['content'] ?? []);
		if ($content === [])
		{
			return 'mixed';
		}

		$schema = null;
		if (isset($content['application/json']['schema']))
		{
			$schema = $content['application/json']['schema'];
		}
		else
		{
			foreach ($content as $entry)
			{
				if (is_array($entry) && isset($entry['schema']))
				{
					$schema = $entry['schema'];
					break;
				}
			}
		}

		if (!is_array($schema))
		{
			return 'mixed';
		}

		return $this->schemaToDoc($schema, $models);
	}

	/**
	 * Map a schema definition to a PHPDoc type string.
	 *
	 * @param array<string, mixed> $schema
	 * @param array<string, Model> $models
	 * @return string
	 */
	private function schemaToDoc(array $schema, array $models): string
	{
		if (isset($schema['$ref']))
		{
			$name = $this->schemaNameFromRef((string)$schema['$ref']);
			return $models[$name]->name ?? $name;
		}

		if (isset($schema['anyOf']) && is_array($schema['anyOf']))
		{
			$parts = [];
			foreach ($schema['anyOf'] as $entry)
			{
				if (is_array($entry))
				{
					$parts[] = $this->schemaToDoc($entry, $models);
				}
			}
			return $parts === [] ? 'mixed' : implode('|', array_unique($parts));
		}

		if (isset($schema['oneOf']) && is_array($schema['oneOf']))
		{
			$parts = [];
			foreach ($schema['oneOf'] as $entry)
			{
				if (is_array($entry))
				{
					$parts[] = $this->schemaToDoc($entry, $models);
				}
			}
			return $parts === [] ? 'mixed' : implode('|', array_unique($parts));
		}

		$type = (string)($schema['type'] ?? '');

		if ($type === 'array')
		{
			$itemSchema = is_array($schema['items'] ?? null) ? $schema['items'] : null;
			if ($itemSchema === null)
			{
				return 'array<int, mixed>';
			}
			$itemDoc = $this->schemaToDoc($itemSchema, $models);
			return "array<int, {$itemDoc}>";
		}

		if ($type === 'object')
		{
			return 'array<string, mixed>';
		}

		return $this->mapScalarType($type);
	}

	/**
	 * Map OpenAPI scalar types to PHP scalar types.
	 *
	 * @param string $type
	 * @return string
	 */
	private function mapScalarType(string $type): string
	{
		return match ($type) {
			'integer' => 'int',
			'number' => 'float',
			'boolean' => 'bool',
			'string' => 'string',
			default => 'mixed',
		};
	}

	/**
	 * Extract normalized tags for a path operation.
	 *
	 * @param array<string, mixed> $item
	 * @return string[]
	 */
	private function extractTags(array $item): array
	{
		$tags = $item['tags'] ?? [];
		if (!is_array($tags) || $tags === [])
		{
			return [];
		}

		$normalized = [];
		foreach ($tags as $tag)
		{
			$tag = trim((string)$tag);
			if ($tag === '')
			{
				continue;
			}
			$normalized[] = $tag;
		}

		return $normalized;
	}

	/**
	 * Pick the primary tag (first) for an operation, defaulting as needed.
	 *
	 * @param string[] $tags
	 * @return string
	 */
	private function extractPrimaryTag(array $tags): string
	{
		return $tags[0] ?? 'default';
	}

	/**
	 * Deduplicate and sort tags for resource-level grouping.
	 *
	 * @param string[] $tags
	 * @return string[]
	 */
	private function uniqueTags(array $tags): array
	{
		if ($tags === [])
		{
			return [];
		}

		$unique = array_values(array_unique($tags));
		sort($unique);

		return $unique;
	}

	/**
	 * Convert a path string into a camelCase resource name.
	 *
	 * @param string $path
	 * @return string
	 */
	private function resourceNameFromPath(string $path): string
	{
		$trimmed = trim($path, '/');
		if ($trimmed === '')
		{
			return 'root';
		}
		$segments = explode('/', $trimmed);
		$segments = array_values(array_filter($segments, static function (string $segment): bool {
			return $segment !== '' && $segment[0] !== '{';
		}));

		$segment = $segments === [] ? $trimmed : (string)end($segments);
		$segment = str_replace(['-', '_'], ' ', $segment);
		$segment = str_replace(' ', '', ucwords($segment));
		$segment = lcfirst($segment);

		return $segment === '' ? 'resource' : $segment;
	}

	/**
	 * Normalize an OpenAPI operationId into a camelCase method name.
	 *
	 * @param string $operationId
	 * @return string
	 */
	private function camelizeOperationId(string $operationId): string
	{
		$operationId = trim($operationId);
		if ($operationId === '')
		{
			return '';
		}
		$parts = preg_split('/[.\/]+/', $operationId);
		$segment = is_array($parts) ? (string)end($parts) : $operationId;

		if (preg_match('/[^a-zA-Z0-9]/', $segment))
		{
			$segment = preg_replace('/[^a-zA-Z0-9]+/', ' ', $segment);
			$segment = str_replace(' ', '', ucwords(strtolower(trim($segment))));
			return $segment === '' ? '' : lcfirst($segment);
		}

		return $segment === '' ? '' : lcfirst($segment);
	}

	/**
	 * Parse a schema reference into a model name.
	 *
	 * @param string $ref
	 * @return string
	 */
	private function schemaNameFromRef(string $ref): string
	{
		$parts = explode('/', $ref);
		return (string)end($parts);
	}
}
