<?php

namespace Maslosoft\ApiFacades\Traits;

use Maslosoft\ApiFacades\Config;

trait ConfigAwareTrait
{
	public Config $config;

	public function getConfig(): Config
	{
		return $this->config;
	}

	public function setConfig(Config $config): static
	{
		$this->config = $config;
		return $this;
	}
}