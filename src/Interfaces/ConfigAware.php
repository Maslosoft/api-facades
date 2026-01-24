<?php

namespace Maslosoft\ApiFacades\Interfaces;

use Maslosoft\ApiFacades\Build\Config;

interface ConfigAware
{
	public function setConfig(Config $config);
}