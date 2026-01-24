<?php

namespace Maslosoft\ApiFacades\Build;

use Maslosoft\ApiFacades\Interfaces\ConfigAware;
use Maslosoft\ApiFacades\Traits\ConfigAwareTrait;

abstract class BaseBuilder implements ConfigAware
{
	use ConfigAwareTrait;

	public function __construct(Config $config)
	{
		$this->config = $config;
	}
}