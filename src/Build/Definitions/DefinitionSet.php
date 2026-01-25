<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Build\Definitions;

/**
 * Aggregated module and operation definitions for rendering.
 */
final class DefinitionSet
{
	/**
	 * @var array<string, ModuleDefinition>
	 */
	public array $modules = [];

	/**
	 * @var array<string, OperationDefinition>
	 */
	public array $operations = [];

	/**
	 * @param array<string, ModuleDefinition> $modules
	 * @param array<string, OperationDefinition> $operations
	 */
	public function __construct(array $modules = [], array $operations = [])
	{
		$this->modules = $modules;
		$this->operations = $operations;
	}
}
