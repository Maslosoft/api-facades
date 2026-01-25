<?php

namespace Maslosoft\ApiFacades\Build;

use Maslosoft\ApiFacades\Interfaces\Builder as BuilderInterface;
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

		$moduleNamer = $this->config->generate->module->namer;

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
			info("Generating $name");
		}
		// TODO: Implement actual code generation based on config
	}
}
