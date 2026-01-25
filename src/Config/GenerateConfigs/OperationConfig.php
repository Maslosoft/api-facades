<?php

namespace Maslosoft\ApiFacades\Config\GenerateConfigs;

use Maslosoft\ApiFacades\Base\GenericOperation;
use Maslosoft\ApiFacades\Config;
use Maslosoft\ApiFacades\Interfaces\ConfigAware;
use Maslosoft\ApiFacades\Interfaces\OperationNamer;
use Maslosoft\ApiFacades\Traits\ConfigAwareTrait;
use Maslosoft\EmbeDi\EmbeDi;

class OperationConfig implements ConfigAware
{
	use ConfigAwareTrait;

	public OperationNamer $namer;

	public string $baseClass;

	/**
	 * @param Config              $config
	 * @param array<string,mixed> $data
	 */
	public function __construct(Config $config, array $data = [])
	{
		$this->config = $config;
		$this->baseClass = $data['baseClass'] ?? GenericOperation::class;
		$namerCfg = $data['namer'];
		$namer = EmbeDi::fly()->apply($namerCfg);
		assert($namer instanceof OperationNamer);
		$this->namer = $namer;

		foreach ($namerCfg['processors'] ?? [] as $processorName => $processorCfg)
		{
			$this->namer->processors[$processorName] = EmbeDi::fly()->apply($processorCfg);
		}
	}
}