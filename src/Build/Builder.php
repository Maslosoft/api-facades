<?php

namespace Maslosoft\ApiFacades\Build;

class Builder
{
	private Config $config;

	public function __construct(Config $config)
	{
		$this->config = $config;
	}

	public function build(): void
	{
		// TODO: Implement actual code generation based on config
	}
}
