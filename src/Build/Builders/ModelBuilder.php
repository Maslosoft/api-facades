<?php

namespace Maslosoft\ApiFacades\Build\Builders;

use Maslosoft\ApiFacades\Build\BaseBuilder;
use Maslosoft\ApiFacades\Interfaces\Builder;

class ModelBuilder extends BaseBuilder implements Builder
{
	public function build(): void
	{
		// TODO:
		// Generate models in $namespace
		// Models should be loaded from openapi specs file
		$namespace = $this->config->output->namespace . '\\Models';
	}

}