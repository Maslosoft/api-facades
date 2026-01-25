<?php

namespace Maslosoft\ApiFacades\Config\GenerateConfigs;

use Maslosoft\ApiFacades\Config;
use Maslosoft\ApiFacades\Interfaces\ConfigAware;
use Maslosoft\ApiFacades\Interfaces\ModuleNamer;
use Maslosoft\ApiFacades\Traits\ConfigAwareTrait;
use Maslosoft\EmbeDi\EmbeDi;

class ModuleConfig implements ConfigAware
{
	use ConfigAwareTrait;

	public ModuleNamer $namer;

	/**
	 * @param Config              $config
	 * @param array<string,mixed> $data
	 */
	public function __construct(Config $config, array $data = [])
	{
		$this->config = $config;
		$namerCfg = $data['namer'];
		$this->namer = EmbeDi::fly()->apply($namerCfg);

		foreach ($namerCfg['processors'] as $processorName => $processorCfg)
		{
			$this->namer->processors[$processorName] = EmbeDi::fly()->apply($processorCfg);
		}
	}
}