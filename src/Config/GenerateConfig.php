<?php

namespace Maslosoft\ApiFacades\Config;

use Maslosoft\ApiFacades\Config;
use Maslosoft\ApiFacades\Config\GenerateConfigs\ModuleConfig;
use Maslosoft\ApiFacades\Interfaces\ConfigAware;
use Maslosoft\ApiFacades\Traits\ConfigAwareTrait;

class GenerateConfig implements ConfigAware
{
	use ConfigAwareTrait;

	public ModuleConfig $module;

	/**
	 * @param Config              $config
	 * @param array<string,mixed> $data
	 */
	public function __construct(Config $config, array $data = [])
	{
		$this->config = $config;
		$this->module = new ModuleConfig($config, (array)$data['modules']);
	}
}