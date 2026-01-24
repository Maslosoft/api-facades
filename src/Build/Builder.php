<?php

namespace Maslosoft\ApiFacades\Build;

use Maslosoft\ApiFacades\Interfaces\Builder as BuilderInterface;
use Maslosoft\Cli\Shared\Io;

class Builder extends BaseBuilder implements BuilderInterface
{
	public function build(): void
	{
		Io::mkdir($this->config->output->path);
		// TODO: Implement actual code generation based on config
	}
}
