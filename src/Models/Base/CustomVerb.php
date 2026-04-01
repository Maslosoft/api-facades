<?php

namespace Maslosoft\ApiFacades\Models\Base;

use Maslosoft\ApiFacades\Base\GenericClient;
use RuntimeException;

class CustomVerb
{
	protected GenericClient $client;

	public function __construct(GenericClient $client)
	{
		$this->client = $client;
	}

	protected function requestData(string $path, string $method, array $params = [], mixed $body = [], array $headers = []): mixed
	{
		return $this->client->getData($path, $method, $params, $body, $headers);
	}

	protected function expectArrayResponse(mixed $data, string $path, string $method): array
	{
		if (!is_array($data))
		{
			$verb = strtoupper($method);
			throw new RuntimeException("Expected array response for {$verb} {$path}.");
		}

		return $data;
	}
}
