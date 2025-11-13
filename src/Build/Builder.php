<?php

namespace Maslosoft\ApiFacades\Build;

class Builder
{
	private Config $config;

	public function __construct(Config $config)
	{
		$this->config = $config;
	}
}
