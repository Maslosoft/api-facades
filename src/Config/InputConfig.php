<?php

namespace Maslosoft\ApiFacades\Config;

/**
 * Input source configuration.
 */
class InputConfig
{
	/**
	 * Location of OpenAPI spec or other input.
	 *
	 * Example: http://127.0.0.1:8000/openapi.json
	 *
	 * @var string
	 */
	public string $location;

	/**
	 * @param array<string,mixed> $data
	 */
	public function __construct(array $data = [])
	{
		$this->location = (string)($data['location'] ?? '');
	}
}
