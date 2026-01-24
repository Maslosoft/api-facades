<?php

namespace Maslosoft\ApiFacades\Config;

use Maslosoft\ApiFacades\Build\Config;
use Maslosoft\ApiFacades\Interfaces\ConfigAware;
use Maslosoft\ApiFacades\Traits\ConfigAwareTrait;

/**
 * Jane-specific configuration.
 */
class JaneConfig implements ConfigAware
{
	use ConfigAwareTrait;
	/**
	 * Whether to annotate Jane classes with @internal
	 *
	 * @var bool
	 */
	public bool $markInternal;

	/**
	 * @param array<string,mixed> $data
	 */
	public function __construct(Config $config, array $data = [])
	{
		$this->markInternal = (bool)($data['markInternal'] ?? false);
	}
}
