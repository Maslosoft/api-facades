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

			$operationSegment = array_pop($segments);
			$moduleSegments = array_values($segments);
			$operationName = $this->normalizeIdentifier((string)$operationSegment, 'operation');
			$verbInfo = $this->resolveVerbInfo($moduleSegments, $operationName);

			$verbSpec = [
				'namespace' => $verbInfo['namespace'],
				'class' => $verbInfo['class'],
				'fqcn' => $verbInfo['fqcn'],
				'path' => $verbInfo['path'],
				'property' => $operationName,
				'methods' => $operations,
				'invokeMethod' => $this->resolveInvokeMethod($operations),
			];

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
		if (!in_array($location, ['path', 'query'], true))
		{
			throw new RuntimeException("Unsupported parameter location '{$location}'.");
		}

		$originalName = (string)($parameter['name'] ?? '');
		$name = $this->normalizeIdentifier($originalName, 'param');
		$required = (bool)($parameter['required'] ?? false);
		$schema = isset($parameter['schema']) && is_array($parameter['schema']) ? $parameter['schema'] : [];
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

		$schema = $this->findContentSchema((array)($requestBody['content'] ?? []));
		if ($schema === null)
		{
			return null;
		}

		return [
			'name' => 'body',
			'originalName' => 'body',
			'location' => 'body',
			'required' => (bool)($requestBody['required'] ?? false),
			'type' => $this->describeInputSchema($schema, $models, $usedModelNames),
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
		if ($content === [])
		{
			return null;
		}

		if (isset($content['application/json']['schema']) && is_array($content['application/json']['schema']))
		{
			return $content['application/json']['schema'];
		}

		foreach ($content as $contentType => $entry)
		{
			if (!is_array($entry))
			{
				continue;
			}
			if (str_contains((string)$contentType, 'json') && isset($entry['schema']) && is_array($entry['schema']))
			{
				return $entry['schema'];
			}
		}

		foreach ($content as $entry)
		{
			if (is_array($entry) && isset($entry['schema']) && is_array($entry['schema']))
			{
				return $entry['schema'];
			}
		}

		return null;
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
		if (isset($schema['$ref']))
		{
			$name = $this->schemaNameFromRef((string)$schema['$ref']);
			$model = $models[$name] ?? null;
			if ($model !== null && $this->shouldGenerateModel($model))
			{
				$usedModelNames[$name] = true;
				$modelInfo = $this->resolveModelClassInfo($name);

				return [
					'typeHint' => '\\' . $modelInfo['fqcn'],
					'docType' => '\\' . $modelInfo['fqcn'],
					'kind' => 'model',
				];
			}

			return $this->describeInputSchema($model?->schema ?? [], $models, $usedModelNames);
		}

		foreach (['allOf', 'oneOf', 'anyOf'] as $compositeKey)
		{
			if (isset($schema[$compositeKey]) && is_array($schema[$compositeKey]))
			{
				return [
					'typeHint' => 'mixed',
					'docType' => 'mixed',
					'kind' => 'mixed',
				];
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

					return [
						'typeHint' => 'array',
						'docType' => 'list<\\' . $modelInfo['fqcn'] . '>',
						'kind' => 'array',
					];
				}
			}

			$itemType = $itemSchema === null
				? 'mixed'
				: ($this->describeInputSchema($itemSchema, $models, $usedModelNames)['typeHint'] ?? 'mixed');

			return [
				'typeHint' => 'array',
				'docType' => 'list<' . $itemType . '>',
				'kind' => 'array',
			];
		}
		if ($type === 'object')
		{
			return [
				'typeHint' => 'array',
				'docType' => 'array<string, mixed>',
				'kind' => 'array',
			];
		}
		if ($type === '')
		{
			return [
				'typeHint' => 'mixed',
				'docType' => 'mixed',
				'kind' => 'mixed',
			];
		}

		return [
			'typeHint' => $this->mapScalarType($type),
			'docType' => $this->mapScalarType($type),
			'kind' => 'scalar',
		];
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

		if ($module['modules'] !== [] || $module['verbs'] !== [])
		{
			$lines[] = '';
		}

		$lines[] = "\tpublic function __construct(private \\" . $this->config->output->namespace . '\\' . $this->config->output->class . ' $client)';
		$lines[] = "\t{";
		foreach ($module['modules'] as $childModule)
		{
			$lines[] = "\t\t\$this->{$childModule['property']} = new \\" . $childModule['fqcn'] . '($client);';
		}
		foreach ($module['verbs'] as $verb)
		{
			$lines[] = "\t\t\$this->{$verb['property']} = new \\" . $verb['fqcn'] . '($client);';
		}
		$lines[] = "\t}";
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
			$lines = array_merge($lines, $this->renderVerbMethod($verb['invokeMethod'], true));
			$lines[] = '';
		}

		$methodKeys = array_keys($verb['methods']);
		foreach ($methodKeys as $index => $methodName)
		{
			$lines = array_merge($lines, $this->renderVerbMethod($verb['methods'][$methodName]));
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
		$signatureParts = [];
		$docLines = [];
		$paramsBuild = [];

		foreach ($parameters as $parameter)
		{
			$type = $parameter['type'];
			$signatureParts[] = $this->renderArgument($parameter['name'], $type['typeHint'], (bool)$parameter['required']);
			if ($type['docType'] !== null && $type['docType'] !== $type['typeHint'])
			{
				$docLines[] = "\t * @param {$type['docType']} \${$parameter['name']}";
			}

			if ($parameter['required'])
			{
				$paramsBuild[] = "\t\t\$params['{$parameter['originalName']}'] = \${$parameter['name']};";
			}
			else
			{
				$paramsBuild[] = "\t\tif (\${$parameter['name']} !== null)";
				$paramsBuild[] = "\t\t{";
				$paramsBuild[] = "\t\t\t\$params['{$parameter['originalName']}'] = \${$parameter['name']};";
				$paramsBuild[] = "\t\t}";
			}
		}

		if ($body !== null)
		{
			$signatureParts[] = $this->renderArgument($body['name'], $body['type']['typeHint'], (bool)$body['required']);
			if ($body['type']['docType'] !== null && $body['type']['docType'] !== $body['type']['typeHint'])
			{
				$docLines[] = "\t * @param {$body['type']['docType']} \${$body['name']}";
			}
		}

		if ($return['docType'] !== null && ($return['phpType'] === 'array' || $return['phpType'] === 'mixed'))
		{
			$docLines[] = "\t * @return {$return['docType']}";
		}

		$methodName = $invoke ? '__invoke' : $method['name'];
		$returnType = $invoke ? $return['phpType'] : $return['phpType'];
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
			$arguments = array_map(
				static fn(array $parameter): string => '$' . $parameter['name'],
				$parameters
			);
			if ($body !== null)
			{
				$arguments[] = '$' . $body['name'];
			}
			$lines[] = "\t\treturn \$this->get(" . implode(', ', $arguments) . ');';
			$lines[] = "\t}";
			return $lines;
		}

		$lines[] = "\t\t\$params = [];";
		foreach ($paramsBuild as $paramsLine)
		{
			$lines[] = $paramsLine;
		}

		$bodyArgument = $body === null ? '[]' : '$' . $body['name'];
		$requestCall = "\$this->requestData('{$method['path']}', '{$method['name']}', \$params, {$bodyArgument})";

		switch ($return['mode'])
		{
			case 'model':
				$lines[] = "\t\t\$data = \$this->expectArrayResponse({$requestCall}, '{$method['path']}', '{$method['name']}');";
				$lines[] = "\t\treturn \$this->client->getHydrator()->hydrate(new {$return['modelFqcn']}(), \$data);";
				break;

			case 'modelList':
				$lines[] = "\t\t\$data = \$this->expectArrayResponse({$requestCall}, '{$method['path']}', '{$method['name']}');";
				$lines[] = "\t\treturn Items::hydrate(\$this->client->getHydrator(), {$return['modelFqcn']}::class, \$data);";
				break;

			case 'rawArray':
				$lines[] = "\t\treturn \$this->expectArrayResponse({$requestCall}, '{$method['path']}', '{$method['name']}');";
				break;

			case 'scalar':
				$lines[] = "\t\t\$data = {$requestCall};";
				$lines = array_merge($lines, $this->renderScalarGuard($return, $method['path'], $method['name']));
				$lines[] = "\t\treturn \$data;";
				break;

			default:
				$lines[] = "\t\treturn {$requestCall};";
				break;
		}

		$lines[] = "\t}";

		return $lines;
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
		if ($typeHint[0] !== '?')
		{
			$typeHint = '?' . $typeHint;
		}

		return "{$typeHint} \${$name} = null";
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
		return $this->primarySchemaType($model->schema) === 'object' && $model->properties !== [];
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
		return is_array($type) && in_array('null', $type, true);
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
