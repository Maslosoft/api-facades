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
		$namerCfg = (array)($data['namer'] ?? []);
		$namer = EmbeDi::fly()->apply($namerCfg);
		assert($namer instanceof OperationNamer);
		$this->namer = $namer;

		foreach ($this->normalizeProcessorConfigs((array)($namerCfg['processors'] ?? [])) as $processorName => $processorCfg)
		{
			$processor = EmbeDi::fly()->apply($processorCfg);
			if ($processor === null)
			{
				continue;
			}
			$this->namer->processors[$processorName] = $processor;
		}
	}

	/**
	 * @param array<string, mixed> $configs
	 * @return array<string, array<string, mixed>>
	 */
	private function normalizeProcessorConfigs(array $configs): array
	{
		$normalized = [];

		foreach ($configs as $name => $config)
		{
			if (!is_array($config))
			{
				continue;
			}
			if (isset($config['class']))
			{
				$normalized[(string)$name] = $config;
				continue;
			}
			if (isset($config['processors']) && is_array($config['processors']))
			{
				foreach ($this->normalizeProcessorConfigs($config['processors']) as $childName => $childConfig)
				{
					$normalized[$childName] = $childConfig;
				}
			}
		}

		return $normalized;
	}
}
