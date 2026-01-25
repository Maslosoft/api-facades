<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Build\Definitions;

use Maslosoft\ApiFacades\Models\Op;

/**
 * Describes a generated operation facade class.
 */
final class OperationDefinition
{
	/**
	 * Operation accessor name (camelCase) used in module methods and magic access.
	 */
	public string $name;

	/**
	 * Operation class short name (StudlyCase).
	 */
	public string $className;

	/**
	 * Fully-qualified class name for the operation facade.
	 */
	public string $fqcn;

	/**
	 * Namespace of the generated operation facade.
	 */
	public string $namespace;

	/**
	 * Operation path for documentation.
	 */
	public string $path;

	/**
	 * Verb map of HTTP method to OpenAPI operation metadata.
	 *
	 * @var array<string, Op>
	 */
	public array $verbs = [];

	/**
	 * @param string $name
	 * @param string $className
	 * @param string $fqcn
	 * @param string $namespace
	 * @param string $path
	 */
	public function __construct(
		string $name,
		string $className,
		string $fqcn,
		string $namespace,
		string $path
	)
	{
		$this->name = $name;
		$this->className = $className;
		$this->fqcn = $fqcn;
		$this->namespace = $namespace;
		$this->path = $path;
	}

	/**
	 * Register a verb implementation for this operation.
	 *
	 * @param string $verb
	 * @param Op $operation
	 */
	public function addVerb(string $verb, Op $operation): void
	{
		$this->verbs[strtolower($verb)] = $operation;
	}
}
