<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Build;

use Maslosoft\ApiFacades\Hydrators\ObjectProperties;
use Maslosoft\ApiFacades\Interfaces\Builder as BuilderInterface;
use Maslosoft\ApiFacades\Interfaces\PathAware;
use Maslosoft\ApiFacades\Interfaces\TagsAware;
use Maslosoft\ApiFacades\Models\Model;
use Maslosoft\ApiFacades\Models\OpenApiSpec;
use Maslosoft\ApiFacades\Support\OpenApiReader;
use RuntimeException;

class Builder extends BaseBuilder implements BuilderInterface
{
	private const array HttpMethods = [
		'get',
		'post',
		'put',
		'patch',
		'delete',
		'head',
		'options',
	];

	public function build(): void
	{
		$outputPath = $this->config->output->path;
		if ($outputPath === '')
		{
			throw new RuntimeException('Output path is empty. Enable output discovery or configure output.path.');
		}

		$this->ensureDirectory($outputPath);

		$spec = (new OpenApiReader())->read($this->config->input->location);
		$context = $this->buildContext($spec);

		foreach ($context['models'] as $model)
		{
			$this->writeFile($model['path'], $this->renderModel($model));
		}
		foreach ($context['verbs'] as $verb)
		{
			$this->writeFile($verb['path'], $this->renderVerb($verb));
		}
		foreach ($context['modules'] as $module)
		{
			$this->writeFile($module['path'], $this->renderModule($module));
		}

		$this->writeFile($context['client']['path'], $this->renderClient($context['client']));
	}

	/**
	 * @return array{
	 *     client: array<string, mixed>,
	 *     modules: array<string, array<string, mixed>>,
	 *     verbs: array<string, array<string, mixed>>,
	 *     models: array<string, array<string, mixed>>
	 * }
	 */
	private function buildContext(OpenApiSpec $spec): array
	{
		$moduleSpecs = [];
		$verbSpecs = [];
		$rootModules = [];
		$rootVerbs = [];
		$usedModelNames = [];
		$pathEntries = [];
		$moduleKeys = [];

		foreach ((array)($spec->raw['paths'] ?? []) as $rawPath => $pathItem)
		{
			if (!is_array($pathItem))
			{
				continue;
			}

			$operations = $this->extractOperations((string)$rawPath, $pathItem, $spec->models, $usedModelNames);
			if ($operations === [])
			{
				continue;
			}

			$tags = $this->collectTags($pathItem);
			$normalizedPath = $this->normalizeFacadePath((string)$rawPath, $tags);
			$segments = $this->extractLiteralSegments($normalizedPath);

			if ($segments === [])
			{
				$fallbackName = $this->fallbackOperationName($operations);
				$segments = [$fallbackName === '' ? 'operation' : $fallbackName];
			}

			$pathEntries[] = [
				'segments' => $segments,
				'operations' => $operations,
			];

			if (count($segments) > 1)
			{
				for ($index = 1; $index < count($segments); $index++)
				{
					$moduleKeys[implode('/', array_slice($segments, 0, $index))] = true;
				}
			}
		}

		foreach ($pathEntries as $entry)
		{
			$segments = $entry['segments'];
			$pathKey = implode('/', $segments);

			if ($pathKey !== '' && isset($moduleKeys[$pathKey]))
			{
				$this->ensureModuleChain($moduleSpecs, $rootModules, $segments);
				$ownVerb = $this->createVerbSpec(
					$segments,
					$this->normalizeIdentifier((string)end($segments), 'operation'),
					$entry['operations'],
					true
				);
				if ($moduleSpecs[$pathKey]['ownVerb'] !== null)
				{
					$ownVerb = $this->mergeModuleOwnVerbSpec($moduleSpecs[$pathKey]['ownVerb'], $ownVerb);
				}
				$moduleSpecs[$pathKey]['ownVerb'] = $ownVerb;
				$verbSpecs[$ownVerb['fqcn']] = $ownVerb;
				continue;
			}

			$operationSegments = $segments;
			$operationSegment = array_pop($operationSegments);
			$moduleSegments = array_values($operationSegments);
			$operationName = $this->normalizeIdentifier((string)$operationSegment, 'operation');
			$verbSpec = $this->createVerbSpec($moduleSegments, $operationName, $entry['operations']);

			$verbSpecs[$verbSpec['fqcn']] = $verbSpec;

			if ($moduleSegments === [])
			{
				$rootVerbs[$operationName] = $verbSpec;
				continue;
			}

			$this->ensureModuleChain($moduleSpecs, $rootModules, $moduleSegments);
			$leafKey = implode('/', $moduleSegments);
			$moduleSpecs[$leafKey]['verbs'][$operationName] = $verbSpec;
		}

		ksort($moduleSpecs);
		ksort($verbSpecs);

		$modelSpecs = $this->buildModelSpecs($usedModelNames, $spec->models);
		ksort($modelSpecs);

		$clientFqcn = $this->config->output->namespace . '\\' . $this->config->output->class;

		return [
			'client' => [
				'namespace' => $this->config->output->namespace,
				'class' => $this->config->output->class,
				'fqcn' => $clientFqcn,
				'path' => $this->config->output->path . DIRECTORY_SEPARATOR . $this->config->output->class . '.php',
				'modules' => $rootModules,
				'verbs' => $rootVerbs,
				'hydratorClass' => $this->resolveConfiguredHydratorClass(),
			],
			'modules' => $moduleSpecs,
			'verbs' => $verbSpecs,
			'models' => $modelSpecs,
		];
	}

	/**
	 * @param string[]                   $moduleSegments
	 * @param array<string, array<string, mixed>> $operations
	 * @return array<string, mixed>
	 */
	private function createVerbSpec(array $moduleSegments, string $operationName, array $operations, bool $moduleOwn = false): array
	{
		$verbInfo = $moduleOwn
			? $this->resolveModuleOwnVerbInfo($moduleSegments)
			: $this->resolveVerbInfo($moduleSegments, $operationName);
		$methods = $moduleOwn
			? $this->groupModuleOwnMethods($operations)
			: $operations;

		return [
			'namespace' => $verbInfo['namespace'],
			'class' => $verbInfo['class'],
			'fqcn' => $verbInfo['fqcn'],
			'path' => $verbInfo['path'],
			'property' => $moduleOwn ? '_own' : $operationName,
			'methods' => $methods,
			'invokeMethod' => $moduleOwn
				? $this->resolveModuleOwnInvokeMethod($methods)
				: $this->resolveInvokeMethod($operations),
			'moduleOwn' => $moduleOwn,
		];
	}

	/**
	 * @param array<string, mixed> $pathItem
	 * @param array<string, Model> $models
	 * @param array<string, bool>  $usedModelNames
	 * @return array<string, array<string, mixed>>
	 */
	private function extractOperations(string $rawPath, array $pathItem, array $models, array &$usedModelNames): array
	{
		$operations = [];
		foreach (self::HttpMethods as $httpMethod)
		{
			$operation = $pathItem[$httpMethod] ?? null;
			if (!is_array($operation))
			{
				continue;
			}

			$operations[$httpMethod] = $this->buildMethodSpec(
				$rawPath,
				$httpMethod,
				$pathItem,
				$operation,
				$models,
				$usedModelNames
			);
		}

		return $operations;
	}

	/**
	 * @param array<string, mixed> $pathItem
	 * @param array<string, mixed> $operation
	 * @param array<string, Model> $models
	 * @param array<string, bool>  $usedModelNames
	 * @return array<string, mixed>
	 */
	private function buildMethodSpec(
		string $rawPath,
		string $httpMethod,
		array $pathItem,
		array $operation,
		array $models,
		array &$usedModelNames
	): array
	{
		$parameters = [];
		foreach ($this->mergeParameters($pathItem, $operation) as $parameter)
		{
			$parameters[] = $this->describeParameter($parameter, $models);
		}

		$bodyParameter = $this->describeRequestBodyParameter(
			isset($operation['requestBody']) && is_array($operation['requestBody']) ? $operation['requestBody'] : null,
			$models,
			$usedModelNames
		);

		$returnSchema = $this->findSuccessResponseSchema((array)($operation['responses'] ?? []));
		$return = $this->describeResponseSchema($returnSchema, $models, $usedModelNames);

		return [
			'name' => strtolower($httpMethod),
			'http' => strtoupper($httpMethod),
			'path' => $rawPath,
			'operationId' => (string)($operation['operationId'] ?? ''),
			'parameters' => $parameters,
			'body' => $bodyParameter,
			'return' => $return,
		];
	}

	/**
	 * @param array<string, mixed> $pathItem
	 * @param array<string, mixed> $operation
	 * @return array<int, array<string, mixed>>
	 */
	private function mergeParameters(array $pathItem, array $operation): array
	{
		$merged = [];

		foreach ([$pathItem['parameters'] ?? [], $operation['parameters'] ?? []] as $source)
		{
			if (!is_array($source))
			{
				continue;
			}
			foreach ($source as $parameter)
			{
				if (!is_array($parameter))
				{
					continue;
				}
				$name = (string)($parameter['name'] ?? '');
				$location = (string)($parameter['in'] ?? '');
				if ($name === '' || $location === '')
				{
					continue;
				}
				$merged[$location . ':' . $name] = $parameter;
			}
		}

		return array_values($merged);
	}

	/**
	 * @param array<string, mixed> $parameter
	 * @param array<string, Model> $models
	 * @return array<string, mixed>
	 */
	private function describeParameter(array $parameter, array $models): array
	{
		$location = (string)($parameter['in'] ?? '');
		if (!in_array($location, ['path', 'query', 'header'], true))
		{
			throw new RuntimeException("Unsupported parameter location '{$location}'.");
		}

		$originalName = (string)($parameter['name'] ?? '');
		$name = $this->normalizeIdentifier($originalName, 'param');
		$required = (bool)($parameter['required'] ?? false);
		$schema = $this->extractParameterSchema($parameter);
		$ignored = [];
		$type = $this->describeInputSchema($schema, $models, $ignored);

		return [
			'name' => $name,
			'originalName' => $originalName,
			'location' => $location,
			'required' => $required || $location === 'path',
			'type' => $type,
		];
	}

	/**
	 * @param array<string, mixed> $parameter
	 * @return array<string, mixed>
	 */
	private function extractParameterSchema(array $parameter): array
	{
		if (isset($parameter['schema']) && is_array($parameter['schema']))
		{
			return $parameter['schema'];
		}

		$schema = [];
		foreach ([
			'$ref',
			'type',
			'format',
			'enum',
			'items',
			'anyOf',
			'oneOf',
			'allOf',
			'nullable',
			'default',
			'additionalProperties',
		] as $key)
		{
			if (!array_key_exists($key, $parameter))
			{
				continue;
			}
			$schema[$key] = $parameter[$key];
		}

		return $schema;
	}

	/**
	 * @param array<string, Model> $models
	 * @param array<string, bool>  $usedModelNames
	 * @return array<string, mixed>|null
	 */
	private function describeRequestBodyParameter(?array $requestBody, array $models, array &$usedModelNames): ?array
	{
		if ($requestBody === null)
		{
			return null;
		}

		$content = $this->findContentDescriptor((array)($requestBody['content'] ?? []));
		if ($content === null)
		{
			return null;
		}

		$schema = $content['schema'];
		$contentType = $content['contentType'];
		$transport = $this->describeRequestBodyTransport($contentType, $schema, $models);
		$flattened = $this->describeFlattenedMultipartBodyParameter(
			$schema,
			$contentType,
			(bool)($requestBody['required'] ?? false),
			$models,
			$usedModelNames
		);

		if ($flattened !== null)
		{
			$flattened['transport'] = $transport;
			return $flattened;
		}

		return [
			'name' => 'body',
			'originalName' => 'body',
			'location' => 'body',
			'required' => (bool)($requestBody['required'] ?? false),
			'type' => $this->describeInputSchema($schema, $models, $usedModelNames),
			'transport' => $transport,
		];
	}

	/**
	 * @param array<string, mixed> $schema
	 * @param array<string, Model> $models
	 * @param array<string, bool>  $usedModelNames
	 * @return array<string, mixed>|null
	 */
	private function describeFlattenedMultipartBodyParameter(
		array $schema,
		string $contentType,
		bool $requestRequired,
		array $models,
		array &$usedModelNames
	): ?array
	{
		if (strtolower($contentType) !== 'multipart/form-data')
		{
			return null;
		}

		$resolvedSchema = $this->resolveSchemaReference($schema, $models);
		if ($this->primarySchemaType($resolvedSchema) !== 'object')
		{
			return null;
		}

		$properties = (array)($resolvedSchema['properties'] ?? []);
		if (count($properties) !== 1)
		{
			return null;
		}

		$propertyName = (string)array_key_first($properties);
		$propertySchema = $properties[$propertyName] ?? null;
		if (!is_array($propertySchema))
		{
			return null;
		}

		$required = $requestRequired || in_array($propertyName, (array)($resolvedSchema['required'] ?? []), true);

		return [
			'name' => $this->normalizeIdentifier($propertyName, 'body'),
			'originalName' => $propertyName,
			'location' => 'body',
			'required' => $required,
			'type' => $this->describeInputSchema($propertySchema, $models, $usedModelNames),
		];
	}

	/**
	 * @param array<string, mixed> $responses
	 * @return array<string, mixed>|null
	 */
	private function findSuccessResponseSchema(array $responses): ?array
	{
		$preferredCodes = [
			'200',
			'201',
			'202',
			'203',
			'204',
			'205',
			'206',
			'default',
		];

		foreach ($preferredCodes as $code)
		{
			$response = $responses[$code] ?? null;
			if (!is_array($response))
			{
				continue;
			}

			$schema = $this->findContentSchema((array)($response['content'] ?? []));
			if ($schema !== null)
			{
				return $schema;
			}

			if ($code === '204' || $code === '205')
			{
				return null;
			}
		}

		foreach ($responses as $code => $response)
		{
			if (!is_array($response))
			{
				continue;
			}
			if ($code !== 'default' && !preg_match('~^2\d\d$~', (string)$code))
			{
				continue;
			}

			$schema = $this->findContentSchema((array)($response['content'] ?? []));
			if ($schema !== null)
			{
				return $schema;
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $content
	 * @return array<string, mixed>|null
	 */
	private function findContentSchema(array $content): ?array
	{
		return $this->findContentDescriptor($content)['schema'] ?? null;
	}

	/**
	 * @param array<string, mixed> $content
	 * @return array{contentType: string, schema: array<string, mixed>}|null
	 */
	private function findContentDescriptor(array $content): ?array
	{
		if ($content === [])
		{
			return null;
		}

		if (isset($content['application/json']['schema']) && is_array($content['application/json']['schema']))
		{
			return [
				'contentType' => 'application/json',
				'schema' => $content['application/json']['schema'],
			];
		}

		foreach ($content as $contentType => $entry)
		{
			if (!is_array($entry))
			{
				continue;
			}
			if (str_contains(strtolower((string)$contentType), 'json') && isset($entry['schema']) && is_array($entry['schema']))
			{
				return [
					'contentType' => (string)$contentType,
					'schema' => $entry['schema'],
				];
			}
		}

		foreach ($content as $contentType => $entry)
		{
			if (!is_array($entry) || !isset($entry['schema']) || !is_array($entry['schema']))
			{
				continue;
			}

			return [
				'contentType' => (string)$contentType,
				'schema' => $entry['schema'],
			];
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $schema
	 * @param array<string, Model> $models
	 * @return array<string, mixed>
	 */
	private function describeRequestBodyTransport(string $contentType, array $schema, array $models): array
	{
		$contentType = strtolower($contentType);

		if ($contentType === 'multipart/form-data')
		{
			return [
				'mode' => 'multipart',
				'contentType' => $contentType,
				'fields' => $this->describeMultipartFields($schema, $models),
			];
		}
		if ($contentType === 'application/x-www-form-urlencoded')
		{
			return [
				'mode' => 'form',
				'contentType' => $contentType,
			];
		}
		if (str_contains($contentType, 'json'))
		{
			return [
				'mode' => 'json',
				'contentType' => $contentType,
			];
		}

		return [
			'mode' => 'raw',
			'contentType' => $contentType,
		];
	}

	/**
	 * @param array<string, mixed> $schema
	 * @param array<string, Model> $models
	 * @return array<int, array{name: string, required: bool, contentType: string}>
	 */
	private function describeMultipartFields(array $schema, array $models): array
	{
		$resolvedSchema = $this->resolveSchemaReference($schema, $models);
		if ($this->primarySchemaType($resolvedSchema) !== 'object')
		{
			return [];
		}

		$required = array_fill_keys((array)($resolvedSchema['required'] ?? []), true);
		$fields = [];
		foreach ((array)($resolvedSchema['properties'] ?? []) as $name => $propertySchema)
		{
			if (!is_array($propertySchema))
			{
				continue;
			}
			$fields[] = [
				'name' => (string)$name,
				'required' => isset($required[$name]),
				'contentType' => (string)($propertySchema['contentMediaType'] ?? ''),
			];
		}

		return $fields;
	}

	/**
	 * @param array<string, mixed> $schema
	 * @param array<string, Model> $models
	 * @return array<string, mixed>
	 */
	private function resolveSchemaReference(array $schema, array $models): array
	{
		$seen = [];
		while (isset($schema['$ref']))
		{
			$name = $this->schemaNameFromRef((string)$schema['$ref']);
			if ($name === '' || isset($seen[$name]))
			{
				break;
			}
			$seen[$name] = true;
			$model = $models[$name] ?? null;
			if ($model === null)
			{
				break;
			}
			$schema = $model->schema;
		}

		return $schema;
	}

	/**
	 * @param array<string, mixed>|null $schema
	 * @param array<string, Model>      $models
	 * @param array<string, bool>       $usedModelNames
	 * @return array<string, mixed>
	 */
	private function describeResponseSchema(?array $schema, array $models, array &$usedModelNames): array
	{
		if ($schema === null)
		{
			return [
				'phpType' => 'mixed',
				'docType' => null,
				'mode' => 'mixed',
			];
		}

		$schema = $this->normalizeSimpleCompositeSchema($schema);

		if (isset($schema['$ref']))
		{
			$name = $this->schemaNameFromRef((string)$schema['$ref']);
			return $this->describeReferencedResponseSchema($name, $models, $usedModelNames);
		}

		foreach (['allOf', 'oneOf', 'anyOf'] as $compositeKey)
		{
			if (isset($schema[$compositeKey]) && is_array($schema[$compositeKey]))
			{
				return [
					'phpType' => 'mixed',
					'docType' => 'mixed',
					'mode' => 'mixed',
				];
			}
		}

		$type = $this->primarySchemaType($schema);

		if ($type === 'array')
		{
			return $this->describeArrayResponseSchema($schema, $models, $usedModelNames);
		}
		if ($type === 'object')
		{
			return [
				'phpType' => 'array',
				'docType' => $this->describeObjectDocType($schema),
				'mode' => 'rawArray',
			];
		}
		if ($type === '')
		{
			return [
				'phpType' => 'mixed',
				'docType' => 'mixed',
				'mode' => 'mixed',
			];
		}

		return [
			'phpType' => $this->mapScalarType($type),
			'docType' => $this->mapScalarType($type),
			'mode' => 'scalar',
			'scalar' => $this->mapScalarType($type),
		];
	}

	/**
	 * @param array<string, Model> $models
	 * @param array<string, bool>  $usedModelNames
	 * @return array<string, mixed>
	 */
	private function describeReferencedResponseSchema(string $name, array $models, array &$usedModelNames): array
	{
		$model = $models[$name] ?? null;
		if ($model === null)
		{
			return [
				'phpType' => 'mixed',
				'docType' => 'mixed',
				'mode' => 'mixed',
			];
		}

		if ($this->shouldGenerateModel($model))
		{
			$usedModelNames[$name] = true;
			$modelInfo = $this->resolveModelClassInfo($name);

			return [
				'phpType' => '\\' . $modelInfo['fqcn'],
				'docType' => '\\' . $modelInfo['fqcn'],
				'mode' => 'model',
				'modelFqcn' => '\\' . $modelInfo['fqcn'],
			];
		}

		return $this->describeResponseSchema($model->schema, $models, $usedModelNames);
	}

	/**
	 * @param array<string, mixed> $schema
	 * @param array<string, Model> $models
	 * @param array<string, bool>  $usedModelNames
	 * @return array<string, mixed>
	 */
	private function describeArrayResponseSchema(array $schema, array $models, array &$usedModelNames): array
	{
		$itemSchema = isset($schema['items']) && is_array($schema['items']) ? $schema['items'] : null;
		if ($itemSchema === null)
		{
			return [
				'phpType' => 'array',
				'docType' => 'list<mixed>',
				'mode' => 'rawArray',
			];
		}

		if (isset($itemSchema['$ref']))
		{
			$name = $this->schemaNameFromRef((string)$itemSchema['$ref']);
			$model = $models[$name] ?? null;
			if ($model !== null && $this->shouldGenerateModel($model))
			{
				$usedModelNames[$name] = true;
				$modelInfo = $this->resolveModelClassInfo($name);

				return [
					'phpType' => 'array',
					'docType' => 'list<\\' . $modelInfo['fqcn'] . '>',
					'mode' => 'modelList',
					'modelFqcn' => '\\' . $modelInfo['fqcn'],
				];
			}
		}

		$itemType = $this->describeResponseSchema($itemSchema, $models, $usedModelNames);
		if (($itemType['mode'] ?? '') === 'scalar')
		{
			return [
				'phpType' => 'array',
				'docType' => 'list<' . $itemType['phpType'] . '>',
				'mode' => 'rawArray',
			];
		}

		if (($itemType['mode'] ?? '') === 'rawArray')
		{
			return [
				'phpType' => 'array',
				'docType' => 'list<array<string, mixed>>',
				'mode' => 'rawArray',
			];
		}

		return [
			'phpType' => 'array',
			'docType' => 'list<mixed>',
			'mode' => 'rawArray',
		];
	}

	/**
	 * @param array<string, mixed> $schema
	 * @param array<string, Model> $models
	 * @param array<string, bool>  $usedModelNames
	 * @return array<string, mixed>
	 */
	private function describeInputSchema(array $schema, array $models, array &$usedModelNames): array
	{
		$schema = $this->normalizeSimpleCompositeSchema($schema);
		$nullable = $this->schemaAllowsNull($schema);

		if (isset($schema['$ref']))
		{
			$name = $this->schemaNameFromRef((string)$schema['$ref']);
			$model = $models[$name] ?? null;
			if ($model !== null && $this->shouldGenerateModel($model))
			{
				$usedModelNames[$name] = true;
				$modelInfo = $this->resolveModelClassInfo($name);

				$type = [
					'typeHint' => '\\' . $modelInfo['fqcn'],
					'docType' => '\\' . $modelInfo['fqcn'],
					'kind' => 'model',
				];
				return $this->applyInputNullability($type, $nullable);
			}

			$type = $this->describeInputSchema($model?->schema ?? [], $models, $usedModelNames);
			return $this->applyInputNullability($type, $nullable);
		}

		foreach (['allOf', 'oneOf', 'anyOf'] as $compositeKey)
		{
			if (isset($schema[$compositeKey]) && is_array($schema[$compositeKey]))
			{
				return $this->applyInputNullability([
					'typeHint' => 'mixed',
					'docType' => 'mixed',
					'kind' => 'mixed',
				], $nullable);
			}
		}

		$type = $this->primarySchemaType($schema);
		if ($type === 'array')
		{
			$itemSchema = isset($schema['items']) && is_array($schema['items']) ? $schema['items'] : null;
			if ($itemSchema !== null && isset($itemSchema['$ref']))
			{
				$name = $this->schemaNameFromRef((string)$itemSchema['$ref']);
				$model = $models[$name] ?? null;
				if ($model !== null && $this->shouldGenerateModel($model))
				{
					$usedModelNames[$name] = true;
					$modelInfo = $this->resolveModelClassInfo($name);

					return $this->applyInputNullability([
						'typeHint' => 'array',
						'docType' => 'list<\\' . $modelInfo['fqcn'] . '>',
						'kind' => 'array',
					], $nullable);
				}
			}

			$itemType = $itemSchema === null
				? 'mixed'
				: ($this->describeInputSchema($itemSchema, $models, $usedModelNames)['typeHint'] ?? 'mixed');

			return $this->applyInputNullability([
				'typeHint' => 'array',
				'docType' => 'list<' . $itemType . '>',
				'kind' => 'array',
			], $nullable);
		}
		if ($type === 'object')
		{
			return $this->applyInputNullability([
				'typeHint' => 'array',
				'docType' => 'array<string, mixed>',
				'kind' => 'array',
			], $nullable);
		}
		if ($type === '')
		{
			return $this->applyInputNullability([
				'typeHint' => 'mixed',
				'docType' => 'mixed',
				'kind' => 'mixed',
			], $nullable);
		}

		return $this->applyInputNullability([
			'typeHint' => $this->mapScalarType($type),
			'docType' => $this->mapScalarType($type),
			'kind' => 'scalar',
		], $nullable);
	}

	/**
	 * @param array<string, bool>  $usedModelNames
	 * @param array<string, Model> $models
	 * @return array<string, array<string, mixed>>
	 */
	private function buildModelSpecs(array $usedModelNames, array $models): array
	{
		$modelSpecs = [];
		$queue = array_keys(array_filter($usedModelNames));

		while ($queue !== [])
		{
			$name = (string)array_shift($queue);
			if (isset($modelSpecs[$name]))
			{
				continue;
			}

			$model = $models[$name] ?? null;
			if ($model === null || !$this->shouldGenerateModel($model))
			{
				continue;
			}

			$modelSpecs[$name] = $this->describeModelSpec($model, $models, $queue);
		}

		$indexedByFqcn = [];
		foreach ($modelSpecs as $spec)
		{
			$indexedByFqcn[$spec['fqcn']] = $spec;
		}

		return $indexedByFqcn;
	}

	/**
	 * @param array<string, Model> $models
	 * @param array<int, string>   $queue
	 * @return array<string, mixed>
	 */
	private function describeModelSpec(Model $model, array $models, array &$queue): array
	{
		$modelInfo = $this->resolveModelClassInfo($model->name);
		$properties = [];

		foreach ($model->properties as $propertyName => $propertySchema)
		{
			if (!is_array($propertySchema))
			{
				continue;
			}

			$properties[] = $this->describeModelProperty(
				(string)$propertyName,
				$propertySchema,
				in_array((string)$propertyName, $model->required, true),
				$models,
				$queue
			);
		}

		return [
			'namespace' => $modelInfo['namespace'],
			'class' => $modelInfo['class'],
			'fqcn' => $modelInfo['fqcn'],
			'path' => $modelInfo['path'],
			'properties' => $properties,
		];
	}

	/**
	 * @param array<string, mixed> $schema
	 * @param array<string, Model> $models
	 * @param array<int, string>   $queue
	 * @return array<string, mixed>
	 */
	private function describeModelProperty(
		string $originalName,
		array $schema,
		bool $required,
		array $models,
		array &$queue
	): array
	{
		$schema = $this->normalizeSimpleCompositeSchema($schema);
		$name = $this->normalizeIdentifier($originalName, 'field');
		$nullable = $this->schemaAllowsNull($schema);
		$default = array_key_exists('default', $schema) ? $schema['default'] : null;
		$hasDefault = array_key_exists('default', $schema);
		$attributes = [];
		$docType = null;
		$typeHint = 'mixed';
		$defaultCode = '';

		if ($name !== $originalName)
		{
			$attributes[] = '#[\Maslosoft\ApiFacades\Hydrators\Attributes\InputField(' . $this->exportValue($originalName) . ')]';
		}

		if (isset($schema['$ref']))
		{
			$nameFromRef = $this->schemaNameFromRef((string)$schema['$ref']);
			$target = $models[$nameFromRef] ?? null;
			if ($target !== null && $this->shouldGenerateModel($target))
			{
				$modelInfo = $this->resolveModelClassInfo($nameFromRef);
				$typeHint = '\\' . $modelInfo['fqcn'];
				$queue[] = $nameFromRef;
			}
			else
			{
				$resolved = $target?->schema ?? [];
				$typeHint = $this->resolveModelPropertyType($resolved);
			}
		}
		else
		{
			$type = $this->primarySchemaType($schema);
			if ($type === 'array')
			{
				$typeHint = 'array';
				$itemSchema = isset($schema['items']) && is_array($schema['items']) ? $schema['items'] : null;
				if ($itemSchema !== null && isset($itemSchema['$ref']))
				{
					$nameFromRef = $this->schemaNameFromRef((string)$itemSchema['$ref']);
					$target = $models[$nameFromRef] ?? null;
					if ($target !== null && $this->shouldGenerateModel($target))
					{
						$modelInfo = $this->resolveModelClassInfo($nameFromRef);
						$attributes[] = '#[\Maslosoft\ApiFacades\Hydrators\Casts\CastArray(\\' . $modelInfo['fqcn'] . '::class)]';
						$docType = 'list<\\' . $modelInfo['fqcn'] . '>';
						$queue[] = $nameFromRef;
					}
					else
					{
						$scalarType = $this->resolveModelPropertyType($target?->schema ?? []);
						if (in_array($scalarType, ['string', 'int', 'float', 'bool'], true))
						{
							$attributes[] = '#[\Maslosoft\ApiFacades\Hydrators\Casts\ScalarArray(\Maslosoft\ApiFacades\Hydrators\Casts\ScalarArray::' . $scalarType . ')]';
							$docType = 'list<' . $scalarType . '>';
						}
						else
						{
							$docType = 'list<mixed>';
						}
					}
				}
				elseif ($itemSchema !== null)
				{
					$itemType = $this->resolveModelPropertyType($itemSchema);
					if (in_array($itemType, ['string', 'int', 'float', 'bool'], true))
					{
						$attributes[] = '#[\Maslosoft\ApiFacades\Hydrators\Casts\ScalarArray(\Maslosoft\ApiFacades\Hydrators\Casts\ScalarArray::' . $itemType . ')]';
					}
					$docType = 'list<' . $itemType . '>';
				}
				else
				{
					$docType = 'list<mixed>';
				}
			}
			elseif ($type === 'object')
			{
				$typeHint = 'array';
				$docType = $this->describeObjectDocType($schema);
			}
			elseif ($type !== '')
			{
				$typeHint = $this->mapScalarType($type);
			}
		}

		if ($nullable && $typeHint !== 'mixed' && $typeHint !== 'array' && $typeHint[0] !== '?')
		{
			$typeHint = '?' . $typeHint;
		}

		if ($hasDefault)
		{
			if ($default === null && $typeHint !== 'mixed' && $typeHint !== 'array' && $typeHint[0] !== '?')
			{
				$typeHint = '?' . $typeHint;
			}
			$defaultCode = ' = ' . $this->exportValue($default);
		}
		elseif (!$required)
		{
			if ($typeHint === 'array')
			{
				$defaultCode = ' = []';
			}
			elseif ($typeHint === 'mixed')
			{
				$defaultCode = ' = null';
			}
			else
			{
				if ($typeHint[0] !== '?')
				{
					$typeHint = '?' . $typeHint;
				}
				$defaultCode = ' = null';
			}
		}

		return [
			'name' => $name,
			'typeHint' => $typeHint,
			'docType' => $docType,
			'attributes' => $attributes,
			'default' => $defaultCode,
		];
	}

	private function resolveModelPropertyType(array $schema): string
	{
		$schema = $this->normalizeSimpleCompositeSchema($schema);
		$type = $this->primarySchemaType($schema);
		if ($type === 'array' || $type === 'object')
		{
			return 'array';
		}
		if ($type === '')
		{
			return 'mixed';
		}

		return $this->mapScalarType($type);
	}

	private function renderClient(array $client): string
	{
		$lines = [
			'<?php',
			'',
			'declare(strict_types=1);',
			'',
			'namespace ' . $client['namespace'] . ';',
			'',
			'use Maslosoft\ApiFacades\Base\GenericClient;',
			'use Maslosoft\ApiFacades\Interfaces\Hydrator;',
			'',
			'/**',
			' * AUTO-GENERATED. Do not edit.',
			' */',
			'class ' . $client['class'] . ' extends GenericClient',
			'{',
		];

		foreach ($client['modules'] as $module)
		{
			$lines[] = "\tpublic \\" . $module['fqcn'] . ' $' . $module['property'] . ';';
		}
		foreach ($client['verbs'] as $verb)
		{
			$lines[] = "\tpublic \\" . $verb['fqcn'] . ' $' . $verb['property'] . ';';
		}

		if ($client['modules'] !== [] || $client['verbs'] !== [])
		{
			$lines[] = '';
		}

		$lines[] = "\tpublic function __construct(?Hydrator \$hydrator = null)";
		$lines[] = "\t{";
		$lines[] = "\t\t\$this->setHydrator(\$hydrator ?? new \\" . $client['hydratorClass'] . '());';
		foreach ($client['modules'] as $module)
		{
			$lines[] = "\t\t\$this->{$module['property']} = new \\" . $module['fqcn'] . '($this);';
		}
		foreach ($client['verbs'] as $verb)
		{
			$lines[] = "\t\t\$this->{$verb['property']} = new \\" . $verb['fqcn'] . '($this);';
		}
		$lines[] = "\t}";
		$lines[] = '}';
		$lines[] = '';

		return implode("\n", $lines);
	}

	private function renderModule(array $module): string
	{
		$lines = [
			'<?php',
			'',
			'declare(strict_types=1);',
			'',
			'namespace ' . $module['namespace'] . ';',
			'',
			'use Maslosoft\ApiFacades\Base\GenericModule;',
			'',
			'/**',
			' * AUTO-GENERATED. Do not edit.',
			' */',
			'final class ' . $module['class'] . ' extends GenericModule',
			'{',
		];

		foreach ($module['modules'] as $childModule)
		{
			$lines[] = "\tpublic \\" . $childModule['fqcn'] . ' $' . $childModule['property'] . ';';
		}
		foreach ($module['verbs'] as $verb)
		{
			$lines[] = "\tpublic \\" . $verb['fqcn'] . ' $' . $verb['property'] . ';';
		}
		if ($module['ownVerb'] !== null)
		{
			$lines[] = "\tprivate \\" . $module['ownVerb']['fqcn'] . ' $_own;';
		}

		if ($module['modules'] !== [] || $module['verbs'] !== [] || $module['ownVerb'] !== null)
		{
			$lines[] = '';
		}

		$lines[] = "\tpublic function __construct(private \\" . $this->config->output->namespace . '\\' . $this->config->output->class . ' $client)';
		$lines[] = "\t{";
		if ($module['ownVerb'] !== null)
		{
			$lines[] = "\t\t\$this->_own = new \\" . $module['ownVerb']['fqcn'] . '($client);';
		}
		foreach ($module['modules'] as $childModule)
		{
			$lines[] = "\t\t\$this->{$childModule['property']} = new \\" . $childModule['fqcn'] . '($client);';
		}
		foreach ($module['verbs'] as $verb)
		{
			$lines[] = "\t\t\$this->{$verb['property']} = new \\" . $verb['fqcn'] . '($client);';
		}
		$lines[] = "\t}";

		if ($module['ownVerb'] !== null)
		{
			$methodKeys = array_keys($module['ownVerb']['methods']);
			foreach ($methodKeys as $methodName)
			{
				$lines[] = '';
				$lines = array_merge($lines, $this->renderDelegatingMethodGroup($module['ownVerb']['methods'][$methodName]));
			}
		}

		$lines[] = '}';
		$lines[] = '';

		return implode("\n", $lines);
	}

	private function renderVerb(array $verb): string
	{
		$lines = [
			'<?php',
			'',
			'declare(strict_types=1);',
			'',
			'namespace ' . $verb['namespace'] . ';',
			'',
			'use Maslosoft\ApiFacades\Hydrators\Items;',
			'use Maslosoft\ApiFacades\Models\Base\CustomVerb;',
			'use RuntimeException;',
			'',
			'/**',
			' * AUTO-GENERATED. Do not edit.',
			' */',
			'final class ' . $verb['class'] . ' extends CustomVerb',
			'{',
		];

		if ($verb['invokeMethod'] !== null)
		{
			$lines = array_merge(
				$lines,
				$verb['moduleOwn']
					? $this->renderVerbMethodGroup($verb['invokeMethod'], true)
					: $this->renderVerbMethod($verb['invokeMethod'], true)
			);
			$lines[] = '';
		}

		$methodKeys = array_keys($verb['methods']);
		foreach ($methodKeys as $index => $methodName)
		{
			$lines = array_merge(
				$lines,
				$verb['moduleOwn']
					? $this->renderVerbMethodGroup($verb['methods'][$methodName])
					: $this->renderVerbMethod($verb['methods'][$methodName])
			);
			if ($index !== array_key_last($methodKeys))
			{
				$lines[] = '';
			}
		}

		$lines[] = '}';
		$lines[] = '';

		return implode("\n", $lines);
	}

	/**
	 * @param array<string, mixed> $method
	 * @return string[]
	 */
	private function renderVerbMethod(array $method, bool $invoke = false): array
	{
		$parameters = $method['parameters'];
		$body = $method['body'];
		$return = $method['return'];
		[$signatureParts, $docLines, $argumentNames] = $this->describeMethodArguments($parameters, $body, $return);

		$methodName = $invoke ? '__invoke' : $method['name'];
		$returnType = $return['phpType'];
		$lines = [];

		if ($docLines !== [])
		{
			$lines[] = "\t/**";
			foreach ($docLines as $docLine)
			{
				$lines[] = $docLine;
			}
			$lines[] = "\t */";
		}

		$lines[] = "\tpublic function {$methodName}(" . implode(', ', $signatureParts) . '): ' . $returnType;
		$lines[] = "\t{";
		if ($invoke)
		{
			$lines[] = "\t\treturn \$this->get(" . implode(', ', $argumentNames) . ');';
			$lines[] = "\t}";
			return $lines;
		}

		$lines = array_merge($lines, $this->indentLines($this->renderVerbMethodExecution($method), 2));

		$lines[] = "\t}";

		return $lines;
	}

	/**
	 * @param array<string, mixed> $methodGroup
	 * @return string[]
	 */
	private function renderDelegatingMethodGroup(array $methodGroup): array
	{
		$descriptor = $this->describeMethodGroup($methodGroup);
		$signatureParts = $descriptor['signatureParts'];
		$docLines = $descriptor['docLines'];
		$argumentNames = $descriptor['argumentNames'];
		$return = $descriptor['return'];

		$lines = [];
		if ($docLines !== [])
		{
			$lines[] = "\t/**";
			foreach ($docLines as $docLine)
			{
				$lines[] = $docLine;
			}
			$lines[] = "\t */";
		}

		$lines[] = "\tpublic function {$methodGroup['name']}(" . implode(', ', $signatureParts) . '): ' . $return['phpType'];
		$lines[] = "\t{";
		$lines[] = "\t\treturn \$this->_own->{$methodGroup['name']}(" . implode(', ', $argumentNames) . ');';
		$lines[] = "\t}";

		return $lines;
	}

	/**
	 * @param array<string, mixed> $methodGroup
	 * @return string[]
	 */
	private function renderVerbMethodGroup(array $methodGroup, bool $invoke = false): array
	{
		$descriptor = $this->describeMethodGroup($methodGroup);
		$signatureParts = $descriptor['signatureParts'];
		$docLines = $descriptor['docLines'];
		$argumentNames = $descriptor['argumentNames'];
		$return = $descriptor['return'];

		$methodName = $invoke ? '__invoke' : $methodGroup['name'];
		$lines = [];

		if ($docLines !== [])
		{
			$lines[] = "\t/**";
			foreach ($docLines as $docLine)
			{
				$lines[] = $docLine;
			}
			$lines[] = "\t */";
		}

		$lines[] = "\tpublic function {$methodName}(" . implode(', ', $signatureParts) . '): ' . $return['phpType'];
		$lines[] = "\t{";
		if ($invoke)
		{
			$lines[] = "\t\treturn \$this->get(" . implode(', ', $argumentNames) . ');';
			$lines[] = "\t}";
			return $lines;
		}

		foreach ($this->sortMethodGroupVariants($methodGroup['variants']) as $variant)
		{
			$condition = $this->renderVariantCondition($variant, $descriptor['arguments']);
			$lines[] = "\t\tif ({$condition})";
			$lines[] = "\t\t{";
			$lines = array_merge($lines, $this->indentLines($this->renderVerbMethodExecution($variant), 3));
			$lines[] = "\t\t}";
		}

		$lines[] = "\t\tthrow new RuntimeException(" . $this->exportValue(
			"No matching endpoint variant for {$methodGroup['name']}()."
		) . ');';
		$lines[] = "\t}";

		return $lines;
	}

	/**
	 * @param array<int, array<string, mixed>> $parameters
	 * @return array{0: string[], 1: string[], 2: string[]}
	 */
	private function describeMethodArguments(array $parameters, ?array $body, array $return): array
	{
		$arguments = $this->collectMethodArguments($parameters, $body);
		usort($arguments, static function (array $left, array $right): int {
			$requiredCompare = ((int)$right['required']) <=> ((int)$left['required']);
			if ($requiredCompare !== 0)
			{
				return $requiredCompare;
			}

			return $left['order'] <=> $right['order'];
		});

		$signatureParts = [];
		$docLines = [];
		$argumentNames = [];

		foreach ($arguments as $argument)
		{
			$type = $argument['type'];
			$signatureParts[] = $this->renderArgument($argument['name'], $type['typeHint'], (bool)$argument['required']);
			$argumentNames[] = '$' . $argument['name'];
			if ($type['docType'] !== null && $type['docType'] !== $type['typeHint'])
			{
				$docLines[] = "\t * @param {$type['docType']} \${$argument['name']}";
			}
		}

		if ($return['docType'] !== null && $return['docType'] !== $return['phpType'])
		{
			$docLines[] = "\t * @return {$return['docType']}";
		}

		return [$signatureParts, $docLines, $argumentNames];
	}

	/**
	 * @param array<string, mixed> $return
	 * @return string[]
	 */
	private function renderScalarGuard(array $return, string $path, string $methodName): array
	{
		$scalar = $return['scalar'] ?? 'mixed';
		$method = strtoupper($methodName);

		return match ($scalar)
		{
			'int' => [
				"\t\tif (!is_int(\$data))",
				"\t\t{",
				"\t\t\tthrow new RuntimeException('Expected int response for {$method} {$path}.');",
				"\t\t}",
			],
			'float' => [
				"\t\tif (!is_int(\$data) && !is_float(\$data))",
				"\t\t{",
				"\t\t\tthrow new RuntimeException('Expected float response for {$method} {$path}.');",
				"\t\t}",
				"\t\t\$data = (float)\$data;",
			],
			'bool' => [
				"\t\tif (!is_bool(\$data))",
				"\t\t{",
				"\t\t\tthrow new RuntimeException('Expected bool response for {$method} {$path}.');",
				"\t\t}",
			],
			'string' => [
				"\t\tif (!is_string(\$data))",
				"\t\t{",
				"\t\t\tthrow new RuntimeException('Expected string response for {$method} {$path}.');",
				"\t\t}",
			],
			default => [],
		};
	}

	private function renderModel(array $model): string
	{
		$lines = [
			'<?php',
			'',
			'declare(strict_types=1);',
			'',
			'namespace ' . $model['namespace'] . ';',
			'',
			'/**',
			' * AUTO-GENERATED. Do not edit.',
			' */',
			'final class ' . $model['class'],
			'{',
		];

		foreach ($model['properties'] as $index => $property)
		{
			if ($property['docType'] !== null)
			{
				$lines[] = "\t/**";
				$lines[] = "\t * @var {$property['docType']}";
				$lines[] = "\t */";
			}
			foreach ($property['attributes'] as $attribute)
			{
				$lines[] = "\t{$attribute}";
			}
			$lines[] = "\tpublic {$property['typeHint']} \${$property['name']}{$property['default']};";
			if ($index !== array_key_last($model['properties']))
			{
				$lines[] = '';
			}
		}

		$lines[] = '}';
		$lines[] = '';

		return implode("\n", $lines);
	}

	private function renderArgument(string $name, string $typeHint, bool $required): string
	{
		if ($required)
		{
			return "{$typeHint} \${$name}";
		}

		if ($typeHint === 'array')
		{
			return "?array \${$name} = null";
		}
		if ($typeHint === 'mixed')
		{
			return "mixed \${$name} = null";
		}

		$typeHint = $this->makeNullableTypeHint($typeHint);

		return "{$typeHint} \${$name} = null";
	}

	/**
	 * @param array<string, mixed> $existing
	 * @param array<string, mixed> $incoming
	 * @return array<string, mixed>
	 */
	private function mergeModuleOwnVerbSpec(array $existing, array $incoming): array
	{
		foreach ($incoming['methods'] as $methodName => $methodGroup)
		{
			if (!isset($existing['methods'][$methodName]))
			{
				$existing['methods'][$methodName] = $methodGroup;
				continue;
			}

			$existing['methods'][$methodName]['variants'] = array_merge(
				$existing['methods'][$methodName]['variants'],
				$methodGroup['variants']
			);
		}

		ksort($existing['methods']);
		$existing['invokeMethod'] = $this->resolveModuleOwnInvokeMethod($existing['methods']);

		return $existing;
	}

	/**
	 * @param array<string, array<string, mixed>> $operations
	 * @return array<string, array<string, mixed>>
	 */
	private function groupModuleOwnMethods(array $operations): array
	{
		$methods = [];
		foreach ($operations as $methodName => $method)
		{
			$methods[$methodName] = [
				'name' => $methodName,
				'variants' => [$method],
			];
		}
		ksort($methods);

		return $methods;
	}

	/**
	 * @param array<string, array<string, mixed>> $methods
	 * @return array<string, mixed>|null
	 */
	private function resolveModuleOwnInvokeMethod(array $methods): ?array
	{
		if (count($methods) !== 1 || !isset($methods['get']))
		{
			return null;
		}

		return $methods['get'];
	}

	/**
	 * @param array<int, array<string, mixed>> $variants
	 * @return array<string, mixed>
	 */
	private function describeMethodGroup(array $methodGroup): array
	{
		$variants = $methodGroup['variants'];
		$arity = count($variants);
		$arguments = [];

		foreach ($variants as $index => $variant)
		{
			foreach ($this->collectMethodArguments($variant['parameters'], $variant['body']) as $argument)
			{
				$key = $argument['key'];
				if (!isset($arguments[$key]))
				{
					$arguments[$key] = $argument + [
						'presentCount' => 0,
						'requiredCount' => 0,
						'typeHints' => [],
						'docTypes' => [],
						'variants' => [],
					];
				}

				$arguments[$key]['presentCount']++;
				$arguments[$key]['requiredCount'] += $argument['required'] ? 1 : 0;
				$arguments[$key]['typeHints'][] = $argument['type']['typeHint'];
				$arguments[$key]['docTypes'][] = $argument['type']['docType'];
				$arguments[$key]['variants'][$index] = true;
			}
		}

		foreach ($arguments as &$argument)
		{
			$argument['required'] = $argument['presentCount'] === $arity
				&& $argument['requiredCount'] === $arity;
			$argument['type']['typeHint'] = $this->mergeTypeHints($argument['typeHints']);
			$argument['type']['docType'] = $this->mergeDocTypes($argument['docTypes']);
			unset($argument['presentCount'], $argument['requiredCount'], $argument['typeHints'], $argument['docTypes']);
		}
		unset($argument);

		$arguments = array_values($arguments);
		usort($arguments, fn(array $left, array $right): int => $this->compareMethodArguments($left, $right));

		$signatureParts = [];
		$docLines = [];
		$argumentNames = [];
		foreach ($arguments as $argument)
		{
			$type = $argument['type'];
			$signatureParts[] = $this->renderArgument($argument['name'], $type['typeHint'], (bool)$argument['required']);
			$argumentNames[] = '$' . $argument['name'];
			if ($type['docType'] !== null && $type['docType'] !== $type['typeHint'])
			{
				$docLines[] = "\t * @param {$type['docType']} \${$argument['name']}";
			}
		}

		$return = $this->mergeMethodReturns($variants);
		if ($return['docType'] !== null && $return['docType'] !== $return['phpType'])
		{
			$docLines[] = "\t * @return {$return['docType']}";
		}

		return [
			'arguments' => $arguments,
			'signatureParts' => $signatureParts,
			'docLines' => $docLines,
			'argumentNames' => $argumentNames,
			'return' => $return,
		];
	}

	/**
	 * @param array<int, array<string, mixed>> $parameters
	 * @return array<int, array<string, mixed>>
	 */
	private function collectMethodArguments(array $parameters, ?array $body): array
	{
		$arguments = [];
		$order = 0;

		foreach ($parameters as $parameter)
		{
			$arguments[] = [
				'key' => $parameter['location'] . ':' . $parameter['originalName'],
				'name' => $parameter['name'],
				'location' => $parameter['location'],
				'required' => (bool)$parameter['required'],
				'type' => $parameter['type'],
				'order' => $order++,
			];
		}
		if ($body !== null)
		{
			$arguments[] = [
				'key' => 'body:' . $body['name'],
				'name' => $body['name'],
				'location' => 'body',
				'required' => (bool)$body['required'],
				'type' => $body['type'],
				'order' => $order++,
			];
		}

		return $arguments;
	}

	private function compareMethodArguments(array $left, array $right): int
	{
		$priorityCompare = $this->methodArgumentPriority($left['location']) <=> $this->methodArgumentPriority($right['location']);
		if ($priorityCompare !== 0)
		{
			return $priorityCompare;
		}

		$requiredCompare = ((int)$right['required']) <=> ((int)$left['required']);
		if ($requiredCompare !== 0)
		{
			return $requiredCompare;
		}

		return $left['order'] <=> $right['order'];
	}

	private function methodArgumentPriority(string $location): int
	{
		return match ($location)
		{
			'path' => 0,
			'query' => 1,
			'header' => 2,
			'body' => 3,
			default => 4,
		};
	}

	private function makeNullableTypeHint(string $typeHint): string
	{
		if ($typeHint === 'mixed' || $typeHint === '?array' || $typeHint[0] === '?')
		{
			return $typeHint;
		}

		if (str_contains($typeHint, '|'))
		{
			$types = explode('|', $typeHint);
			if (!in_array('null', $types, true))
			{
				$types[] = 'null';
			}

			return implode('|', $types);
		}

		return '?' . $typeHint;
	}

	/**
	 * @param array<int, string> $typeHints
	 */
	private function mergeTypeHints(array $typeHints): string
	{
		$types = [];
		foreach ($typeHints as $typeHint)
		{
			if ($typeHint === '' || $typeHint === 'mixed')
			{
				return 'mixed';
			}

			foreach (explode('|', ltrim($typeHint, '?')) as $type)
			{
				$type = trim($type);
				if ($type === '' || $type === 'null')
				{
					continue;
				}
				$types[$type] = true;
			}
		}

		if ($types === [])
		{
			return 'mixed';
		}

		ksort($types);
		return implode('|', array_keys($types));
	}

	/**
	 * @param array<int, string|null> $docTypes
	 */
	private function mergeDocTypes(array $docTypes): ?string
	{
		$types = [];
		foreach ($docTypes as $docType)
		{
			if ($docType === null || $docType === '')
			{
				continue;
			}
			if ($docType === 'mixed')
			{
				return 'mixed';
			}
			$types[$docType] = true;
		}

		if ($types === [])
		{
			return null;
		}
		if (count($types) === 1)
		{
			return (string)array_key_first($types);
		}

		ksort($types);
		return implode('|', array_keys($types));
	}

	/**
	 * @param array<int, array<string, mixed>> $variants
	 * @return array<string, string|null>
	 */
	private function mergeMethodReturns(array $variants): array
	{
		$phpTypes = [];
		$docTypes = [];

		foreach ($variants as $variant)
		{
			$phpType = (string)$variant['return']['phpType'];
			if ($phpType === 'mixed')
			{
				return [
					'phpType' => 'mixed',
					'docType' => 'mixed',
				];
			}
			$phpTypes[$phpType] = true;
			$docTypes[] = $variant['return']['docType'];
		}

		ksort($phpTypes);
		$phpType = implode('|', array_keys($phpTypes));

		return [
			'phpType' => $phpType,
			'docType' => $this->mergeDocTypes($docTypes) ?? $phpType,
		];
	}

	/**
	 * @param array<int, array<string, mixed>> $variants
	 * @return array<int, array<string, mixed>>
	 */
	private function sortMethodGroupVariants(array $variants): array
	{
		usort($variants, function (array $left, array $right): int {
			$requiredCompare = $this->countRequiredMethodArguments($right) <=> $this->countRequiredMethodArguments($left);
			if ($requiredCompare !== 0)
			{
				return $requiredCompare;
			}

			$pathCompare = $this->countPathMethodArguments($right) <=> $this->countPathMethodArguments($left);
			if ($pathCompare !== 0)
			{
				return $pathCompare;
			}

			$totalCompare = $this->countMethodArguments($right) <=> $this->countMethodArguments($left);
			if ($totalCompare !== 0)
			{
				return $totalCompare;
			}

			return strcmp($left['path'], $right['path']);
		});

		return $this->pruneUnreachableMethodVariants($variants);
	}

	/**
	 * @param array<int, array<string, mixed>> $variants
	 * @return array<int, array<string, mixed>>
	 */
	private function pruneUnreachableMethodVariants(array $variants): array
	{
		$filtered = [];
		foreach ($variants as $variant)
		{
			$covered = false;
			foreach ($filtered as $candidate)
			{
				if ($this->variantCoversVariant($candidate, $variant))
				{
					$covered = true;
					break;
				}
			}

			if (!$covered)
			{
				$filtered[] = $variant;
			}
		}

		return $filtered;
	}

	private function countMethodArguments(array $method): int
	{
		return count($method['parameters']) + ($method['body'] === null ? 0 : 1);
	}

	private function countRequiredMethodArguments(array $method): int
	{
		$required = 0;
		foreach ($method['parameters'] as $parameter)
		{
			$required += $parameter['required'] ? 1 : 0;
		}
		if ($method['body'] !== null && $method['body']['required'])
		{
			$required++;
		}

		return $required;
	}

	private function countPathMethodArguments(array $method): int
	{
		$count = 0;
		foreach ($method['parameters'] as $parameter)
		{
			if ($parameter['location'] === 'path')
			{
				$count++;
			}
		}

		return $count;
	}

	private function variantCoversVariant(array $candidate, array $variant): bool
	{
		$candidateArguments = $this->indexMethodArgumentsByKey($candidate);
		$variantArguments = $this->indexMethodArgumentsByKey($variant);

		foreach ($variantArguments as $key => $argument)
		{
			if (!isset($candidateArguments[$key]))
			{
				return false;
			}
		}

		foreach ($candidateArguments as $key => $argument)
		{
			if (!$argument['required'])
			{
				continue;
			}
			if (!isset($variantArguments[$key]) || !$variantArguments[$key]['required'])
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $method
	 * @return array<string, array<string, mixed>>
	 */
	private function indexMethodArgumentsByKey(array $method): array
	{
		$arguments = [];
		foreach ($this->collectMethodArguments($method['parameters'], $method['body']) as $argument)
		{
			$arguments[$argument['key']] = $argument;
		}

		return $arguments;
	}

	/**
	 * @param array<string, mixed> $method
	 * @param array<int, array<string, mixed>> $arguments
	 */
	private function renderVariantCondition(array $method, array $arguments): string
	{
		$variantArguments = [];
		foreach ($this->collectMethodArguments($method['parameters'], $method['body']) as $argument)
		{
			$variantArguments[$argument['key']] = $argument;
		}

		$conditions = [];
		foreach ($arguments as $argument)
		{
			$name = '$' . $argument['name'];
			if (!isset($variantArguments[$argument['key']]))
			{
				$conditions[] = "{$name} === null";
				continue;
			}
			if ($variantArguments[$argument['key']]['required'])
			{
				$conditions[] = "{$name} !== null";
			}
		}

		return $conditions === [] ? 'true' : implode(' && ', $conditions);
	}

	/**
	 * @param array<string, mixed> $method
	 * @return string[]
	 */
	private function renderVerbMethodExecution(array $method): array
	{
		$parameters = $method['parameters'];
		$body = $method['body'];
		$return = $method['return'];
		$paramsBuild = [];
		$headersBuild = [];

		foreach ($parameters as $parameter)
		{
			$targetName = $parameter['location'] === 'header' ? 'headers' : 'params';
			if ($parameter['required'])
			{
				${$targetName . 'Build'}[] = "\${$targetName}['{$parameter['originalName']}'] = \${$parameter['name']};";
				continue;
			}

			${$targetName . 'Build'}[] = "if (\${$parameter['name']} !== null)";
			${$targetName . 'Build'}[] = '{';
			${$targetName . 'Build'}[] = "\t\${$targetName}['{$parameter['originalName']}'] = \${$parameter['name']};";
			${$targetName . 'Build'}[] = '}';
		}

		if ($body === null)
		{
			$requestCall = "\$this->requestData('{$method['path']}', '{$method['name']}', \$params, [], \$headers)";
		}
		else
		{
			$bodyArgument = '$' . $body['name'];
			$requestOptions = $this->exportValue($body['transport'] ?? []);
			$requestCall = "\$this->requestData('{$method['path']}', '{$method['name']}', \$params, {$bodyArgument}, \$headers, {$requestOptions})";
		}

		$lines = [
			'$params = [];',
			'$headers = [];',
		];
		foreach ($paramsBuild as $paramsLine)
		{
			$lines[] = $paramsLine;
		}
		foreach ($headersBuild as $headerLine)
		{
			$lines[] = $headerLine;
		}

		switch ($return['mode'])
		{
			case 'model':
				$lines[] = "\$data = \$this->expectArrayResponse({$requestCall}, '{$method['path']}', '{$method['name']}');";
				$lines[] = "return \$this->client->getHydrator()->hydrate(new {$return['modelFqcn']}(), \$data);";
				break;

			case 'modelList':
				$lines[] = "\$data = \$this->expectArrayResponse({$requestCall}, '{$method['path']}', '{$method['name']}');";
				$lines[] = "return Items::hydrate(\$this->client->getHydrator(), {$return['modelFqcn']}::class, \$data);";
				break;

			case 'rawArray':
				$lines[] = "return \$this->expectArrayResponse({$requestCall}, '{$method['path']}', '{$method['name']}');";
				break;

			case 'scalar':
				$lines[] = "\$data = {$requestCall};";
				$lines = array_merge($lines, $this->renderScalarGuardUnindented($return, $method['path'], $method['name']));
				$lines[] = 'return $data;';
				break;

			default:
				$lines[] = "return {$requestCall};";
				break;
		}

		return $lines;
	}

	/**
	 * @param array<string, mixed> $return
	 * @return string[]
	 */
	private function renderScalarGuardUnindented(array $return, string $path, string $methodName): array
	{
		return array_map(
			static fn(string $line): string => preg_replace('/^\t\t/', '', $line, 1) ?? $line,
			$this->renderScalarGuard($return, $path, $methodName)
		);
	}

	/**
	 * @param string[] $lines
	 * @return string[]
	 */
	private function indentLines(array $lines, int $level): array
	{
		$prefix = str_repeat("\t", $level);

		return array_map(static function (string $line) use ($prefix): string {
			if ($line === '')
			{
				return $line;
			}

			return $prefix . $line;
		}, $lines);
	}

	/**
	 * @param array<string, array<string, mixed>> $moduleSpecs
	 * @param array<string, array<string, mixed>> $rootModules
	 * @param string[]                            $moduleSegments
	 */
	private function ensureModuleChain(array &$moduleSpecs, array &$rootModules, array $moduleSegments): void
	{
		$current = [];
		$parentKey = '';

		foreach ($moduleSegments as $segment)
		{
			$current[] = $segment;
			$key = implode('/', $current);
			if (!isset($moduleSpecs[$key]))
			{
				$moduleInfo = $this->resolveModuleInfo($current);
				$moduleSpecs[$key] = [
					'key' => $key,
					'namespace' => $moduleInfo['namespace'],
					'class' => $moduleInfo['class'],
					'fqcn' => $moduleInfo['fqcn'],
					'path' => $moduleInfo['path'],
					'property' => $this->normalizeIdentifier($segment, 'module'),
					'modules' => [],
					'verbs' => [],
					'ownVerb' => null,
				];
			}

			if ($parentKey === '')
			{
				$rootModules[$moduleSpecs[$key]['property']] = $moduleSpecs[$key];
			}
			else
			{
				$moduleSpecs[$parentKey]['modules'][$moduleSpecs[$key]['property']] = $moduleSpecs[$key];
			}

			$parentKey = $key;
		}
	}

	/**
	 * @param array<string, array<string, mixed>> $operations
	 */
	private function fallbackOperationName(array $operations): string
	{
		foreach ($operations as $operation)
		{
			$operationId = trim((string)($operation['operationId'] ?? ''));
			if ($operationId === '')
			{
				continue;
			}
			$parts = preg_split('/[.\/]+/', $operationId) ?: [];
			$segment = (string)end($parts);
			if ($segment !== '')
			{
				return $this->normalizeIdentifier($segment, 'operation');
			}
		}

		return 'operation';
	}

	/**
	 * @param array<string, array<string, mixed>> $operations
	 * @return array<string, mixed>|null
	 */
	private function resolveInvokeMethod(array $operations): ?array
	{
		if (count($operations) !== 1 || !isset($operations['get']))
		{
			return null;
		}

		return $operations['get'];
	}

	/**
	 * @param array<string, mixed> $pathItem
	 * @return string[]
	 */
	private function collectTags(array $pathItem): array
	{
		$tags = [];
		foreach (self::HttpMethods as $httpMethod)
		{
			$operation = $pathItem[$httpMethod] ?? null;
			if (!is_array($operation))
			{
				continue;
			}
			foreach ((array)($operation['tags'] ?? []) as $tag)
			{
				$tag = trim((string)$tag);
				if ($tag === '')
				{
					continue;
				}
				$tags[] = $tag;
			}
		}

		return array_values(array_unique($tags));
	}

	/**
	 * @param string[] $tags
	 */
	private function normalizeFacadePath(string $rawPath, array $tags): string
	{
		$moduleNamer = $this->config->generate->modules->namer;
		if ($moduleNamer instanceof PathAware)
		{
			$moduleNamer->setPath($rawPath);
		}
		if ($moduleNamer instanceof TagsAware)
		{
			$moduleNamer->setTags($tags);
		}

		$name = trim((string)$moduleNamer->getName(), '/');
		if ($name !== '')
		{
			return $name;
		}

		return trim($rawPath, '/');
	}

	/**
	 * @return string[]
	 */
	private function extractLiteralSegments(string $path): array
	{
		$segments = preg_split('~/+~', trim($path, '/')) ?: [];
		$segments = array_filter($segments, static function (string $segment): bool {
			return $segment !== '' && !preg_match('~^\{.+}$~', $segment);
		});

		return array_values($segments);
	}

	/**
	 * @return array{namespace: string, class: string, fqcn: string, path: string}
	 */
	private function resolveModuleInfo(array $segments): array
	{
		$classSegment = (string)end($segments);
		$class = $this->normalizeClassName($classSegment, 'Module') . 'Module';
		$namespace = $this->config->output->namespace . '\\Modules';

		$parentSegments = $segments;
		array_pop($parentSegments);
		if ($parentSegments !== [])
		{
			$namespace .= '\\' . implode('\\', array_map(
				fn(string $segment): string => $this->normalizeClassName($segment, 'Module'),
				$parentSegments
			));
		}

		$relative = ['Modules'];
		foreach ($parentSegments as $segment)
		{
			$relative[] = $this->normalizeClassName($segment, 'Module');
		}
		$path = $this->config->output->path . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $relative)
			. DIRECTORY_SEPARATOR . $class . '.php';

		return [
			'namespace' => $namespace,
			'class' => $class,
			'fqcn' => $namespace . '\\' . $class,
			'path' => $path,
		];
	}

	/**
	 * @return array{namespace: string, class: string, fqcn: string, path: string}
	 */
	private function resolveVerbInfo(array $moduleSegments, string $operationName): array
	{
		$class = $this->normalizeClassName($operationName, 'Operation') . 'Verb';
		$namespace = $this->config->output->namespace . '\\Verbs';
		$relative = ['Verbs'];

		foreach ($moduleSegments as $segment)
		{
			$classSegment = $this->normalizeClassName($segment, 'Module');
			$namespace .= '\\' . $classSegment;
			$relative[] = $classSegment;
		}

		return [
			'namespace' => $namespace,
			'class' => $class,
			'fqcn' => $namespace . '\\' . $class,
			'path' => $this->config->output->path . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $relative)
				. DIRECTORY_SEPARATOR . $class . '.php',
		];
	}

	/**
	 * @param string[] $moduleSegments
	 * @return array{namespace: string, class: string, fqcn: string, path: string}
	 */
	private function resolveModuleOwnVerbInfo(array $moduleSegments): array
	{
		$classSegment = (string)end($moduleSegments);
		$class = $this->normalizeClassName($classSegment, 'Module') . 'Verb';
		$namespace = $this->config->output->namespace . '\\ModuleVerbs';
		$relative = ['ModuleVerbs'];

		$parentSegments = $moduleSegments;
		array_pop($parentSegments);
		foreach ($parentSegments as $segment)
		{
			$classSegment = $this->normalizeClassName($segment, 'Module');
			$namespace .= '\\' . $classSegment;
			$relative[] = $classSegment;
		}

		return [
			'namespace' => $namespace,
			'class' => $class,
			'fqcn' => $namespace . '\\' . $class,
			'path' => $this->config->output->path . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $relative)
				. DIRECTORY_SEPARATOR . $class . '.php',
		];
	}

	/**
	 * @return array{namespace: string, class: string, fqcn: string, path: string}
	 */
	private function resolveModelClassInfo(string $schemaName): array
	{
		$parts = preg_split('/[.\/\\\\]+/', trim($schemaName)) ?: [];
		$parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
		if ($parts === [])
		{
			$parts = ['GeneratedModel'];
		}

		$className = $this->normalizeClassName((string)array_pop($parts), 'GeneratedModel');
		$namespace = $this->config->output->namespace . '\\Models';
		$relative = ['Models'];

		foreach ($parts as $part)
		{
			$segment = $this->normalizeClassName($part, 'Model');
			$namespace .= '\\' . $segment;
			$relative[] = $segment;
		}

		return [
			'namespace' => $namespace,
			'class' => $className,
			'fqcn' => $namespace . '\\' . $className,
			'path' => $this->config->output->path . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $relative)
				. DIRECTORY_SEPARATOR . $className . '.php',
		];
	}

	private function resolveConfiguredHydratorClass(): string
	{
		$class = $this->config->raw['generate']['models']['hydrator']['class'] ?? ObjectProperties::class;
		if (!is_string($class) || $class === '')
		{
			return ObjectProperties::class;
		}

		return ltrim($class, '\\');
	}

	private function shouldGenerateModel(Model $model): bool
	{
		return $this->primarySchemaType($this->normalizeSimpleCompositeSchema($model->schema)) === 'object' && $model->properties !== [];
	}

	/**
	 * @param array<string, mixed> $schema
	 */
	private function primarySchemaType(array $schema): string
	{
		$type = $schema['type'] ?? '';
		if (is_array($type))
		{
			foreach ($type as $candidate)
			{
				if ($candidate === 'null')
				{
					continue;
				}
				return (string)$candidate;
			}

			return '';
		}

		return is_string($type) ? $type : '';
	}

	/**
	 * @param array<string, mixed> $schema
	 */
	private function schemaAllowsNull(array $schema): bool
	{
		if (($schema['nullable'] ?? false) === true)
		{
			return true;
		}

		$type = $schema['type'] ?? null;
		if (is_array($type) && in_array('null', $type, true))
		{
			return true;
		}

		foreach (['anyOf', 'oneOf'] as $key)
		{
			if (!isset($schema[$key]) || !is_array($schema[$key]))
			{
				continue;
			}
			foreach ($schema[$key] as $entry)
			{
				if (is_array($entry) && $this->schemaRepresentsNull($entry))
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $type
	 * @return array<string, mixed>
	 */
	private function applyInputNullability(array $type, bool $nullable): array
	{
		if (!$nullable)
		{
			return $type;
		}
		if (($type['typeHint'] ?? 'mixed') !== 'mixed' && !str_starts_with((string)$type['typeHint'], '?'))
		{
			$type['typeHint'] = '?' . $type['typeHint'];
		}
		if (($type['docType'] ?? null) !== null && $type['docType'] !== 'mixed' && !str_contains((string)$type['docType'], 'null'))
		{
			$type['docType'] .= '|null';
		}

		return $type;
	}

	/**
	 * @param array<string, mixed> $schema
	 * @return array<string, mixed>
	 */
	private function normalizeSimpleCompositeSchema(array $schema): array
	{
		foreach (['anyOf', 'oneOf'] as $key)
		{
			if (!isset($schema[$key]) || !is_array($schema[$key]))
			{
				continue;
			}

			$nullable = $this->schemaAllowsNull($schema);
			$variants = [];
			foreach ($schema[$key] as $entry)
			{
				if (!is_array($entry))
				{
					continue;
				}
				if ($this->schemaRepresentsNull($entry))
				{
					$nullable = true;
					continue;
				}
				$variants[] = $entry;
			}

			if (count($variants) !== 1)
			{
				return $schema;
			}

			$metadata = $schema;
			unset($metadata[$key]);
			$resolved = array_replace_recursive($variants[0], $metadata);
			if ($nullable)
			{
				$resolved['nullable'] = true;
			}

			return $resolved;
		}

		if (isset($schema['allOf']) && is_array($schema['allOf']))
		{
			$variants = array_values(array_filter($schema['allOf'], 'is_array'));
			if (count($variants) === 1)
			{
				$metadata = $schema;
				unset($metadata['allOf']);
				return array_replace_recursive($variants[0], $metadata);
			}
		}

		return $schema;
	}

	/**
	 * @param array<string, mixed> $schema
	 */
	private function schemaRepresentsNull(array $schema): bool
	{
		$type = $schema['type'] ?? null;
		if ($type === 'null')
		{
			return true;
		}
		if (is_array($type))
		{
			$withoutNull = array_values(array_filter($type, static fn(mixed $entry): bool => $entry !== 'null'));
			return $withoutNull === [] && in_array('null', $type, true);
		}

		return false;
	}

	private function mapScalarType(string $type): string
	{
		return match ($type)
		{
			'integer' => 'int',
			'number' => 'float',
			'boolean' => 'bool',
			'string' => 'string',
			default => 'mixed',
		};
	}

	private function schemaNameFromRef(string $ref): string
	{
		$parts = explode('/', $ref);
		return (string)end($parts);
	}

	/**
	 * @param array<string, mixed> $schema
	 */
	private function describeObjectDocType(array $schema): string
	{
		$additional = $schema['additionalProperties'] ?? null;
		if (is_array($additional))
		{
			$type = $this->primarySchemaType($additional);
			if ($type !== '')
			{
				return 'array<string, ' . $this->mapScalarType($type) . '>';
			}
		}

		return 'array<string, mixed>';
	}

	private function normalizeIdentifier(string $value, string $fallback): string
	{
		$value = trim($value);
		if ($value === '')
		{
			return $fallback;
		}

		if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $value))
		{
			$result = lcfirst($value);
		}
		else
		{
			$parts = preg_split('/[^A-Za-z0-9]+/', $value) ?: [];
			$parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
			if ($parts === [])
			{
				return $fallback;
			}

			$first = strtolower(array_shift($parts));
			$result = $first;
			foreach ($parts as $part)
			{
				$result .= ucfirst(strtolower($part));
			}
		}

		if ($result === '' || ctype_digit($result[0]))
		{
			$result = $fallback . ucfirst($result);
		}
		if ($this->isReservedWord($result))
		{
			$result .= 'Value';
		}

		return $result;
	}

	private function normalizeClassName(string $value, string $fallback): string
	{
		$value = trim($value);
		if ($value === '')
		{
			return $fallback;
		}

		if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $value))
		{
			$result = ucfirst($value);
		}
		else
		{
			$parts = preg_split('/[^A-Za-z0-9]+/', $value) ?: [];
			$parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
			if ($parts === [])
			{
				return $fallback;
			}

			$result = '';
			foreach ($parts as $part)
			{
				$result .= ucfirst(strtolower($part));
			}
		}

		if ($result === '' || ctype_digit($result[0]))
		{
			$result = $fallback . ucfirst($result);
		}
		if ($this->isReservedWord($result))
		{
			$result .= 'Type';
		}

		return $result;
	}

	private function isReservedWord(string $value): bool
	{
		static $reserved = [
			'abstract',
			'and',
			'array',
			'as',
			'break',
			'callable',
			'case',
			'catch',
			'class',
			'clone',
			'const',
			'continue',
			'declare',
			'default',
			'do',
			'echo',
			'else',
			'elseif',
			'empty',
			'enddeclare',
			'endfor',
			'endforeach',
			'endif',
			'endswitch',
			'endwhile',
			'enum',
			'eval',
			'exit',
			'extends',
			'final',
			'finally',
			'fn',
			'for',
			'foreach',
			'function',
			'global',
			'goto',
			'if',
			'implements',
			'include',
			'include_once',
			'instanceof',
			'insteadof',
			'interface',
			'isset',
			'list',
			'match',
			'namespace',
			'new',
			'or',
			'print',
			'private',
			'protected',
			'public',
			'readonly',
			'require',
			'require_once',
			'return',
			'static',
			'switch',
			'throw',
			'trait',
			'try',
			'unset',
			'use',
			'var',
			'while',
			'xor',
			'yield',
		];

		return in_array(strtolower($value), $reserved, true);
	}

	private function exportValue(mixed $value): string
	{
		return var_export($value, true);
	}

	private function writeFile(string $path, string $content): void
	{
		$this->ensureDirectory(dirname($path));
		file_put_contents($path, $content);
	}

	private function ensureDirectory(string $path): void
	{
		if (is_dir($path))
		{
			return;
		}

		mkdir($path, 0777, true);
	}
}
