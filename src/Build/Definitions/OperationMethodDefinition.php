<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Build\Definitions;

/**
 * Represents an operation accessor method emitted in a module class.
 */
final class OperationMethodDefinition
{
	/**
	 * Method name exposed on the module (camelCase).
	 */
	public string $name;

	/**
	 * Operation facade class (FQCN) returned by the method.
	 */
	public string $fqcn;

	/**
	 * @param string $name
	 * @param string $fqcn
	 */
	public function __construct(string $name, string $fqcn)
	{
		$this->name = $name;
		$this->fqcn = $fqcn;
	}
}
