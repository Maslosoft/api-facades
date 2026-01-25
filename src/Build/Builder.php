<?php

namespace Maslosoft\ApiFacades\Build;

use Maslosoft\ApiFacades\Interfaces\Builder as BuilderInterface;
use Maslosoft\ApiFacades\Interfaces\OperationIdAware;
use Maslosoft\ApiFacades\Interfaces\PathAware;
use Maslosoft\ApiFacades\Interfaces\TagsAware;
use Maslosoft\ApiFacades\Support\OpenApiReader;
use Maslosoft\Cli\Shared\Io;
use function Maslosoft\ApiFacades\Logging\info;

class Builder extends BaseBuilder implements BuilderInterface
{
	public function build(): void
	{
		Io::mkdir($this->config->output->path);
		$oar = new OpenApiReader();
		$specs = $oar->read($this->config->input->location);

		$moduleNamer = $this->config->generate->modules->namer;
		$baseClass = $this->config->generate->modules->baseClass;

		$operationNamer = $this->config->generate->operations->namer;

		foreach($specs->resources as $resource)
		{
			if($moduleNamer instanceof PathAware)
			{
				$moduleNamer->setPath($resource->path);
			}
			if($moduleNamer instanceof TagsAware)
			{
				$moduleNamer->setTags($resource->tags);
			}
			$name = $moduleNamer->getName();
			$modulesChain = preg_split('~[./]~', $name);

			foreach($modulesChain as $moduleName)
			{
				// TODO: Implement actual code generation of modules that:
				// Each class name has first letter upper case.
				// 1. Are nested based on parts:
				// 1.a. All should reside in base namespace as defined in output.namespace, and be named for example admin -> Acme\Test01\Admin
				// 1.b. If there are more parts, each should have namespace consisting of previous names too, and be named for example admin/tenant ->  Acme\Test01\Admin\Tenant
				// 1.c. if part is just a single item, it should just generate module class with first letter uppercase
				// 2. Each module should extend from base class defined as above $baseClass
				// 3. Module should expose methods for operations
			}
			foreach ($resource->operations as $operation)
			{
				if($operationNamer instanceof OperationIdAware)
				{
					$operationNamer->setOperationId($operation->id);
				}
				$operationName = $operationNamer->getName();
				// 1. TODO: Add each operation to the module
			}
		}
	}
}
