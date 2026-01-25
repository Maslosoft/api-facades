<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Build;

use Maslosoft\ApiFacades\Build\Collectors\DefinitionCollector;
use Maslosoft\ApiFacades\Build\Definitions\DefinitionSet;
use Maslosoft\ApiFacades\Build\Render\ModuleRenderer;
use Maslosoft\ApiFacades\Build\Render\OperationRenderer;
use Maslosoft\ApiFacades\Build\Support\NameNormalizer;
use Maslosoft\ApiFacades\Build\Support\NamespaceHelper;
use Maslosoft\ApiFacades\Build\Support\TemplateLoader;
use Maslosoft\ApiFacades\Interfaces\Builder as BuilderInterface;
use Maslosoft\ApiFacades\Support\OpenApiReader;
use Maslosoft\Cli\Shared\Io;
use function Maslosoft\ApiFacades\Logging\info;

/**
 * Generates module and operation facades based on an OpenAPI specification.
 */
class Builder extends BaseBuilder implements BuilderInterface
{
	/**
	 * Default generated client class when none is configured.
	 */
	private const string DefaultGeneratedClient = 'Generated\\Client';

	/**
	 * Build facade classes into the configured output directory.
	 */
	public function build(): void
	{
		Io::mkdir($this->config->output->path);
		$specs = (new OpenApiReader())->read($this->config->input->location);

		$normalizer = new NameNormalizer();
		$collector = new DefinitionCollector(
			$this->config,
			$this->config->generate->modules->namer,
			$this->config->generate->operations->namer,
			$normalizer
		);
		$definitions = $collector->collect($specs);

		$templateLoader = new TemplateLoader(__DIR__ . '/templates');
		$moduleTemplate = $templateLoader->load('module.md');
		$operationTemplate = $templateLoader->load('operation.md');

		$namespaces = new NamespaceHelper();
		$generatedClient = $this->resolveGeneratedClientFqcn();

		$moduleRenderer = new ModuleRenderer(
			$namespaces,
			$moduleTemplate,
			$generatedClient,
			$this->config->generate->modules->baseClass
		);
		$operationRenderer = new OperationRenderer(
			$namespaces,
			$operationTemplate,
			$generatedClient,
			$this->config->generate->operations->baseClass,
			$normalizer
		);

		$this->writeOperations($definitions, $operationRenderer, $namespaces);
		$this->writeModules($definitions, $moduleRenderer, $namespaces);
	}

	/**
	 * Write generated module classes to disk.
	 *
	 * @param DefinitionSet $definitions
	 * @param ModuleRenderer $renderer
	 * @param NamespaceHelper $namespaces
	 */
	private function writeModules(
		DefinitionSet $definitions,
		ModuleRenderer $renderer,
		NamespaceHelper $namespaces
	): void
	{
		if ($definitions->modules === [])
		{
			return;
		}
		$baseNamespace = trim($this->config->output->namespace, '\\');
		$outputBase = rtrim($this->config->output->path, DIRECTORY_SEPARATOR);
		ksort($definitions->modules);

		foreach ($definitions->modules as $module)
		{
			$contents = $renderer->render($module, $definitions->operations);
			$relative = $namespaces->relativeNamespace($module->namespace, $baseNamespace);
			$directory = $outputBase;
			if ($relative !== '')
			{
				$directory .= DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative);
			}
			Io::mkdir($directory);
			$filePath = $directory . DIRECTORY_SEPARATOR . $module->className . '.php';
			file_put_contents($filePath, $contents);
			info("Generated module {$module->fqcn} at {$filePath}");
		}
	}

	/**
	 * Write generated operation classes to disk.
	 *
	 * @param DefinitionSet $definitions
	 * @param OperationRenderer $renderer
	 * @param NamespaceHelper $namespaces
	 */
	private function writeOperations(
		DefinitionSet $definitions,
		OperationRenderer $renderer,
		NamespaceHelper $namespaces
	): void
	{
		if ($definitions->operations === [])
		{
			return;
		}
		$baseNamespace = trim($this->config->output->namespace, '\\');
		$outputBase = rtrim($this->config->output->path, DIRECTORY_SEPARATOR);
		ksort($definitions->operations);

		foreach ($definitions->operations as $operation)
		{
			$contents = $renderer->render($operation);
			$relative = $namespaces->relativeNamespace($operation->namespace, $baseNamespace);
			$directory = $outputBase;
			if ($relative !== '')
			{
				$directory .= DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative);
			}
			Io::mkdir($directory);
			$filePath = $directory . DIRECTORY_SEPARATOR . $operation->className . '.php';
			file_put_contents($filePath, $contents);
			info("Generated operation {$operation->fqcn} at {$filePath}");
		}
	}

	/**
	 * Resolve the generated client FQCN for use in facades.
	 *
	 * @return string
	 */
	private function resolveGeneratedClientFqcn(): string
	{
		$raw = $this->config->raw;
		$candidate = $raw['output']['generatedClient'] ?? $raw['generate']['generatedClient'] ?? null;
		if (is_string($candidate) && $candidate !== '')
		{
			return ltrim($candidate, '\\');
		}
		return self::DefaultGeneratedClient;
	}
}
