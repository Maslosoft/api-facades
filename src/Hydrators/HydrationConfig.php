<?php

namespace Maslosoft\ApiFacades\Hydrators;

class HydrationConfig
{
	public const string CamelizeAuto = 'auto';
	public const string CamelizeEnabled = 'enabled';
	public const string CamelizeDisabled = 'disabled';

	public string $camelizeInput;

	public function __construct(string $camelizeInput = self::CamelizeAuto)
	{
		$this->camelizeInput = $camelizeInput;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public function shouldCamelizeInput(array $data): bool
	{
		return match ($this->camelizeInput)
		{
			self::CamelizeEnabled => true,
			self::CamelizeDisabled => false,
			default => $this->hasSnakeCaseKeys($data),
		};
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function hasSnakeCaseKeys(array $data): bool
	{
		foreach ($data as $key => $_)
		{
			if (is_string($key) && str_contains($key, '_'))
			{
				return true;
			}
		}

		return false;
	}
}
