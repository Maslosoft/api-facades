<?php

namespace Maslosoft\ApiFacades\Config;

/**
 * Jane-specific configuration.
 */
class JaneConfig
{
	/**
	 * Whether to annotate Jane classes with @internal
	 *
	 * @var bool
	 */
	public bool $markInternal;

	/**
	 * @param array<string,mixed> $data
	 */
	public function __construct(array $data = [])
	{
		$this->markInternal = (bool)($data['markInternal'] ?? false);
	}
}
