<?php

namespace Maslosoft\ApiFacades\Build;

use Maslosoft\Cli\Shared\Io;

class Builder
{
	private Config $config;

	public function __construct(Config $config)
	{
		$this->config = $config;
	}

	public function build(): void
	{
		Io::mkdir($this->config->output->path);
		// TODO: Implement actual code generation based on config
	}
}
