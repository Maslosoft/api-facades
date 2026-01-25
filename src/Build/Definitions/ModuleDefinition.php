<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Build\Definitions;

/**
 * Describes a generated module facade class.
 */
final class ModuleDefinition
{
	/**
	 * Namespace of the module class.
	 */
	public string $namespace;

	/**
	 * Short class name of the module.
	 */
	public string $className;

	/**
	 * Fully-qualified module class name.
	 */
	public string $fqcn;

	/**
	 * Tag used for documentation.
	 */
	public string $tag;

	/**
	 * Module accessor method name (camelCase) when referenced from a parent module.
	 */
	public string $accessor;

	/**
	 * Nested module accessors (camelCase => FQCN).
	 *
	 * @var array<string, string>
	 */
	public array $children = [];

	/**
	 * Operation accessors exposed by this module.
	 *
	 * @var array<string, OperationMethodDefinition>
	 */
	public array $operations = [];

	/**
	 * @param string $namespace
	 * @param string $className
	 * @param string $fqcn
	 * @param string $tag
	 * @param string $accessor
	 */
	public function __construct(
		string $namespace,
		string $className,
		string $fqcn,
		string $tag,
		string $accessor
	)
	{
		$this->namespace = $namespace;
		$this->className = $className;
		$this->fqcn = $fqcn;
		$this->tag = $tag;
		$this->accessor = $accessor;
	}

	/**
	 * Register a nested module accessor.
	 *
	 * @param string $name
	 * @param string $fqcn
	 */
	public function addChild(string $name, string $fqcn): void
	{
		$this->children[$name] = $fqcn;
	}

	/**
	 * Register an operation accessor.
	 *
	 * @param OperationMethodDefinition $operation
	 */
	public function addOperation(OperationMethodDefinition $operation): void
	{
		$this->operations[$operation->name] = $operation;
	}
}
