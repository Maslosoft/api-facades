<?php

namespace Maslosoft\ApiFacades\Config;

use Maslosoft\ApiFacades\Config;
use Maslosoft\ApiFacades\Config\GenerateConfigs\ModuleConfig;
use Maslosoft\ApiFacades\Config\GenerateConfigs\OperationConfig;
use Maslosoft\ApiFacades\Interfaces\ConfigAware;
use Maslosoft\ApiFacades\Traits\ConfigAwareTrait;

class GenerateConfig implements ConfigAware
{
	use ConfigAwareTrait;

	public ModuleConfig $modules;

	public OperationConfig $operations;


	/**
	 * @param Config              $config
	 * @param array<string,mixed> $data
	 */
	public function __construct(Config $config, array $data = [])
	{
		$this->config = $config;
		$this->modules = new ModuleConfig($config, (array)$data['modules']);
		$this->operations = new OperationConfig($config, (array)$data['operations']);
	}
}