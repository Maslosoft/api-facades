<?php

namespace Maslosoft\ApiFacades\Interfaces;

use Maslosoft\ApiFacades\Config;

interface ConfigAware
{
	public function setConfig(Config $config);
}