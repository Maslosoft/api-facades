<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Build\Collectors;

use Maslosoft\ApiFacades\Build\Definitions\DefinitionSet;
use Maslosoft\ApiFacades\Build\Definitions\ModuleDefinition;
use Maslosoft\ApiFacades\Build\Definitions\OperationDefinition;
use Maslosoft\ApiFacades\Build\Definitions\OperationMethodDefinition;
use Maslosoft\ApiFacades\Build\Support\NameNormalizer;
use Maslosoft\ApiFacades\Config;
use Maslosoft\ApiFacades\Interfaces\ModuleNamer;
use Maslosoft\ApiFacades\Interfaces\OperationNamer;
use Maslosoft\ApiFacades\Interfaces\PathAware;
use Maslosoft\ApiFacades\Interfaces\TagsAware;
use Maslosoft\ApiFacades\Models\Op;
use Maslosoft\ApiFacades\Models\OpenApiSpec;
use Maslosoft\ApiFacades\Models\Resource;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Collects module and operation definitions from an OpenAPI specification.
 */
final class DefinitionCollector
{
	/**
	 * @var Config
	 */
	private Config $config;

	/**
	 * @var ModuleNamer
	 */
	private ModuleNamer $moduleNamer;

	/**
	 * @var OperationNamer
	 */
	private OperationNamer $operationNamer;

	/**
	 * @var NameNormalizer
	 */
	private NameNormalizer $normalizer;

	/**
	 * @param Config $config
	 * @param ModuleNamer $moduleNamer
	 * @param OperationNamer $operationNamer
	 * @param NameNormalizer $normalizer
	 */
	public function __construct(
		Config $config,
		ModuleNamer $moduleNamer,
		OperationNamer $operationNamer,
		NameNormalizer $normalizer
	)
	{
		$this->config = $config;
		$this->moduleNamer = $moduleNamer;
		$this->operationNamer = $operationNamer;
		$this->normalizer = $normalizer;
	}

	/**
	 * Collect definitions for modules and operations.
	 *
	 * @param OpenApiSpec $specs
	 * @return DefinitionSet
	 */
	public function collect(OpenApiSpec $specs): DefinitionSet
	{
		$definitions = new DefinitionSet();
		foreach ($specs->resources as $resource)
		{
			$chain = $this->resolveModuleChain($resource);
			$lastModule = $this->registerModules($definitions, $chain, $resource);
			if ($lastModule === null)
			{
				continue;
			}
			$this->registerOperations($definitions, $lastModule, $resource);
		}
		return $definitions;
	}

	/**
	 * Resolve module chain parts for a resource.
	 *
	 * @param Resource $resource
	 * @return string[]
	 */
	private function resolveModuleChain(Resource $resource): array
	{
		if ($this->moduleNamer instanceof PathAware)
		{
			$this->moduleNamer->setPath($resource->path);
		}
		if ($this->moduleNamer instanceof TagsAware)
		{
			$this->moduleNamer->setTags($resource->tags);
		}
		$name = trim($this->moduleNamer->getName());
		if ($name === '')
		{
			$name = $resource->name;
		}
		$parts = preg_split('~[./]~', $name);
		if (!is_array($parts) || $parts === [])
		{
			return [$resource->name];
		}
		$parts = array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
		return $parts === [] ? [$resource->name] : $parts;
	}

	/**
	 * Register module definitions for a chain.
	 *
	 * @param DefinitionSet $definitions
	 * @param string[] $chain
	 * @param Resource $resource
	 * @return ModuleDefinition|null
	 */
	private function registerModules(DefinitionSet $definitions, array $chain, Resource $resource): ?ModuleDefinition
	{
		$baseNamespace = trim($this->config->output->namespace, '\\');
		$namespaceParts = [];
		$parent = null;
		$tag = $this->resolveModuleTag($resource);

		foreach ($chain as $part)
		{
			$className = $this->normalizer->className($part);
			$accessor = $this->normalizer->accessorName($part, 'module');
			$namespace = $baseNamespace;
			if ($namespaceParts !== [])
			{
				$namespace .= '\\' . implode('\\', $namespaceParts);
			}
			$fqcn = $namespace . '\\' . $className;
			if (!isset($definitions->modules[$fqcn]))
			{
				$definitions->modules[$fqcn] = new ModuleDefinition(
					$namespace,
					$className,
					$fqcn,
					$tag,
					$accessor
				);
			}
			$current = $definitions->modules[$fqcn];
			if ($parent instanceof ModuleDefinition)
			{
				$parent->addChild($accessor, $fqcn);
			}
			$namespaceParts[] = $className;
			$parent = $current;
		}

		return $parent;
	}

	/**
	 * Register operation definitions and module accessors.
	 *
	 * @param DefinitionSet $definitions
	 * @param ModuleDefinition $module
	 * @param Resource $resource
	 */
	private function registerOperations(DefinitionSet $definitions, ModuleDefinition $module, Resource $resource): void
	{
		foreach ($resource->verbs as $verb => $operation)
		{
			$name = $this->resolveOperationName($operation, $resource, (string)$verb);
			if ($name === '')
			{
				continue;
			}
			$operationDefinition = $this->ensureOperationDefinition($definitions, $module, $name, $operation);
			$operationDefinition->addVerb((string)$verb, $operation);

			$methodDefinition = new OperationMethodDefinition($name, $operationDefinition->fqcn);
			$module->addOperation($methodDefinition);
		}
	}

	/**
	 * Ensure an operation definition exists and return it.
	 *
	 * @param DefinitionSet $definitions
	 * @param ModuleDefinition $module
	 * @param string $name
	 * @param Op $operation
	 * @return OperationDefinition
	 */
	private function ensureOperationDefinition(
		DefinitionSet $definitions,
		ModuleDefinition $module,
		string $name,
		Op $operation
	): OperationDefinition
	{
		$operationsNamespace = $module->namespace . '\\Operations';
		$className = $this->normalizer->accessorToClass($name);
		$fqcn = $operationsNamespace . '\\' . $className;
		if (!isset($definitions->operations[$fqcn]))
		{
			$definitions->operations[$fqcn] = new OperationDefinition(
				$name,
				$className,
				$fqcn,
				$operationsNamespace,
				$operation->path
			);
		}

		return $definitions->operations[$fqcn];
	}

	/**
	 * Resolve module tag for documentation.
	 *
	 * @param Resource $resource
	 * @return string
	 */
	private function resolveModuleTag(Resource $resource): string
	{
		if ($resource->tags === [])
		{
			return 'default';
		}
		return (string)($resource->tags[0] ?? 'default');
	}

	/**
	 * Resolve the operation name for a given OpenAPI operation.
	 *
	 * @param Op $operation
	 * @param Resource $resource
	 * @param string $verb
	 * @return string
	 */
	private function resolveOperationName(Op $operation, Resource $resource, string $verb): string
	{
		$this->applyOperationId($operation->operationId);
		$name = trim($this->operationNamer->getName());
		if ($name !== '')
		{
			$normalized = $this->normalizer->methodName($name);
			if ($normalized !== '')
			{
				return $normalized;
			}
		}
		if ($operation->janeMethod !== '')
		{
			$normalized = $this->normalizer->methodName($operation->janeMethod);
			if ($normalized !== '')
			{
				return $normalized;
			}
		}
		$fallback = $resource->name . ucfirst(strtolower($verb));
		return $this->normalizer->methodName($fallback);
	}

	/**
	 * Apply operation id to namers that support it.
	 *
	 * @param string $operationId
	 */
	private function applyOperationId(string $operationId): void
	{
		if (!method_exists($this->operationNamer, 'setOperationId'))
		{
			return;
		}
		$method = new ReflectionMethod($this->operationNamer, 'setOperationId');
		$params = $method->getParameters();
		if ($params === [])
		{
			return;
		}
		$type = $params[0]->getType();
		if ($type instanceof ReflectionNamedType && $type->getName() !== 'string')
		{
			return;
		}
		$this->operationNamer->setOperationId($operationId);
	}
}
