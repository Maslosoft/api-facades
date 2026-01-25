<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Build\Render;

use Maslosoft\ApiFacades\Build\Definitions\OperationDefinition;
use Maslosoft\ApiFacades\Build\Support\NameNormalizer;
use Maslosoft\ApiFacades\Build\Support\NamespaceHelper;
use Maslosoft\ApiFacades\Models\Op;
use Maslosoft\ApiFacades\Support\Tpl;

/**
 * Renders operation facade classes from definition objects.
 */
final class OperationRenderer
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
	 * @var NameNormalizer
	 */
	private NameNormalizer $normalizer;

	/**
	 * Supported HTTP verbs exposed on operations.
	 *
	 * @var string[]
	 */
	private const array SupportedVerbOrder = [
		'get',
		'post',
		'put',
		'delete',
		'patch',
	];

	/**
	 * @param NamespaceHelper $namespaces
	 * @param string $template
	 * @param string $generatedClientFqcn
	 * @param string $baseClassFqcn
	 * @param NameNormalizer $normalizer
	 */
	public function __construct(
		NamespaceHelper $namespaces,
		string $template,
		string $generatedClientFqcn,
		string $baseClassFqcn,
		NameNormalizer $normalizer
	)
	{
		$this->namespaces = $namespaces;
		$this->template = $template;
		$this->generatedClientFqcn = ltrim($generatedClientFqcn, '\\');
		$this->baseClassFqcn = ltrim($baseClassFqcn, '\\');
		$this->normalizer = $normalizer;
	}

	/**
	 * Render an operation class.
	 *
	 * @param OperationDefinition $definition
	 * @return string
	 */
	public function render(OperationDefinition $definition): string
	{
		$useLines = $this->buildUseLines($definition);
		$verbMethods = $this->buildVerbMethods($definition->verbs);
		$extends = $this->namespaces->shortClassName($this->baseClassFqcn);
		$baseNamespace = $this->namespaces->namespaceFromFqcn($this->baseClassFqcn);

		return Tpl::render($this->template, [
			'ns' => $definition->namespace,
			'class' => $definition->className,
			'tag' => $definition->name,
			'path' => $definition->path,
			'extends' => $extends,
			'genClientFqcn' => $this->generatedClientFqcn,
			'genClientShort' => $this->namespaces->shortClassName($this->generatedClientFqcn),
			'baseClassFqcn' => $this->baseClassFqcn,
			'baseClassNamespace' => $baseNamespace,
			'uses' => $useLines,
			'verbMethods' => $verbMethods,
		]);
	}

	/**
	 * Build use statements.
	 *
	 * @param OperationDefinition $definition
	 * @return string
	 */
	private function buildUseLines(OperationDefinition $definition): string
	{
		$useLines = [
			"use {$this->generatedClientFqcn};",
		];
		$baseNamespace = $this->namespaces->namespaceFromFqcn($this->baseClassFqcn);
		if ($baseNamespace !== $definition->namespace)
		{
			$useLines[] = "use {$this->baseClassFqcn};";
		}
		$useLines = array_values(array_unique($useLines));
		sort($useLines);
		return implode("\n", $useLines);
	}

	/**
	 * Build method implementations for supported verbs.
	 *
	 * @param array<string, Op> $verbs
	 * @return string
	 */
	private function buildVerbMethods(array $verbs): string
	{
		$blocks = [];
		foreach (self::SupportedVerbOrder as $verb)
		{
			if (!isset($verbs[$verb]))
			{
				continue;
			}
			$operation = $verbs[$verb];
			$clientMethod = $operation->janeMethod !== '' ? $operation->janeMethod : $operation->operationId;
			$clientMethod = $this->normalizer->methodName($clientMethod);
			if ($clientMethod === '')
			{
				continue;
			}
			$returnDoc = trim($operation->returnDoc);
			$returnDoc = $returnDoc === '' ? 'mixed' : $returnDoc;
			$blocks[] = implode("\n", [
				"\t/**",
				"\t * {$operation->http} {$operation->path}",
				"\t * @return {$returnDoc}",
				"\t */",
				"\tpublic function {$verb}(...\$arguments): mixed",
				"\t{",
				"\t\treturn \$this->client->{$clientMethod}(...\$arguments);",
				"\t}",
			]);
		}
		return implode("\n\n", $blocks);
		}
	}
