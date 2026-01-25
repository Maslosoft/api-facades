<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Build\Render;

use Maslosoft\ApiFacades\Build\Definitions\ModuleDefinition;
use Maslosoft\ApiFacades\Build\Definitions\OperationDefinition;
use Maslosoft\ApiFacades\Build\Support\NamespaceHelper;
use Maslosoft\ApiFacades\Support\Tpl;

/**
 * Renders module facade classes from definition objects.
 */
final class ModuleRenderer
{
	/**
	 * @var NamespaceHelper
	 */
	private NamespaceHelper $namespaces;

	/**
	 * @var string
	 */
	private string $template;

	/**
	 * @var string
	 */
	private string $generatedClientFqcn;

	/**
	 * @var string
	 */
	private string $baseClassFqcn;

	/**
	 * @param NamespaceHelper $namespaces
	 * @param string $template
	 * @param string $generatedClientFqcn
	 * @param string $baseClassFqcn
	 */
	public function __construct(
		NamespaceHelper $namespaces,
		string $template,
		string $generatedClientFqcn,
		string $baseClassFqcn
	)
	{
		$this->namespaces = $namespaces;
		$this->template = $template;
		$this->generatedClientFqcn = ltrim($generatedClientFqcn, '\\');
		$this->baseClassFqcn = ltrim($baseClassFqcn, '\\');
	}

	/**
	 * Render a module class using definitions.
	 *
	 * @param ModuleDefinition $module
	 * @param array<string, OperationDefinition> $operations
	 * @return string
	 */
	public function render(ModuleDefinition $module, array $operations): string
	{
		$useLines = $this->buildUseLines($module, $operations);
		$magicMap = $this->buildMagicMap($module);
		$moduleMethods = $this->buildModuleMethods($module);
		$operationMethods = $this->buildOperationMethods($module, $operations);
		$extends = $this->namespaces->shortClassName($this->baseClassFqcn);
		$baseNamespace = $this->namespaces->namespaceFromFqcn($this->baseClassFqcn);

		return Tpl::render($this->template, [
			'ns' => $module->namespace,
			'class' => $module->className,
			'tag' => $module->tag,
			'extends' => $extends,
			'genClientFqcn' => $this->generatedClientFqcn,
			'genClientShort' => $this->namespaces->shortClassName($this->generatedClientFqcn),
			'baseClassFqcn' => $this->baseClassFqcn,
			'baseClassNamespace' => $baseNamespace,
			'uses' => $useLines,
			'moduleMethods' => $moduleMethods,
			'operationMethods' => $operationMethods,
			'magicMap' => $magicMap,
		]);
	}

	/**
	 * Build use statements for the module.
	 *
	 * @param ModuleDefinition $module
	 * @param array<string, OperationDefinition> $operations
	 * @return string
	 */
	private function buildUseLines(ModuleDefinition $module, array $operations): string
	{
		$useLines = [
			"use {$this->generatedClientFqcn};",
		];
		$baseNamespace = $this->namespaces->namespaceFromFqcn($this->baseClassFqcn);
		if ($baseNamespace !== $module->namespace)
		{
			$useLines[] = "use {$this->baseClassFqcn};";
		}
		foreach ($module->children as $fqcn)
		{
			$useLines[] = "use {$fqcn};";
		}
		foreach ($module->operations as $operation)
		{
			if (!isset($operations[$operation->fqcn]))
			{
				continue;
			}
			$useLines[] = "use {$operation->fqcn};";
		}
		$useLines = array_values(array_unique($useLines));
		sort($useLines);
		return implode("\n", $useLines);
	}

	/**
	 * Build module accessors.
	 *
	 * @param ModuleDefinition $module
	 * @return string
	 */
	private function buildModuleMethods(ModuleDefinition $module): string
	{
		if ($module->children === [])
		{
			return '';
		}
		ksort($module->children);
		$blocks = [];
		foreach ($module->children as $name => $fqcn)
		{
			$short = $this->namespaces->shortClassName($fqcn);
			$blocks[] = implode("\n", [
				"\t/**",
				"\t * @return {$short}",
				"\t */",
				"\tpublic function {$name}(): {$short}",
				"\t{",
				"\t\treturn new {$short}(\$this->client);",
				"\t}",
			]);
		}
		return implode("\n\n", $blocks);
	}

	/**
	 * Build operation accessors.
	 *
	 * @param ModuleDefinition $module
	 * @param array<string, OperationDefinition> $operations
	 * @return string
	 */
	private function buildOperationMethods(ModuleDefinition $module, array $operations): string
	{
		if ($module->operations === [])
		{
			return '';
		}
		$methodBlocks = [];
		$operationNames = array_keys($module->operations);
		sort($operationNames);
		foreach ($operationNames as $name)
		{
			$operation = $module->operations[$name];
			if (!isset($operations[$operation->fqcn]))
			{
				continue;
			}
			$short = $this->namespaces->shortClassName($operation->fqcn);
			$methodBlocks[] = implode("\n", [
				"\t/**",
				"\t * @return {$short}",
				"\t */",
				"\tpublic function {$operation->name}(): {$short}",
				"\t{",
				"\t\treturn new {$short}(\$this->client);",
				"\t}",
			]);
		}
		return implode("\n\n", $methodBlocks);
	}

	/**
	 * Build magic map entries for __get.
	 *
	 * @param ModuleDefinition $module
	 * @return string
	 */
	private function buildMagicMap(ModuleDefinition $module): string
	{
		$entries = [];
		foreach ($module->children as $name => $fqcn)
		{
			$short = $this->namespaces->shortClassName($fqcn);
			$entries[] = "\t\t\t'{$name}' => fn (): {$short} => new {$short}(\$this->client),";
		}
		foreach ($module->operations as $operation)
		{
			$short = $this->namespaces->shortClassName($operation->fqcn);
			$entries[] = "\t\t\t'{$operation->name}' => fn (): {$short} => new {$short}(\$this->client),";
		}
		if ($entries === [])
		{
			return '';
		}
		return implode("\n", $entries);
	}
}
