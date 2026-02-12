<?php

namespace Tests\Models\Clients;

use Maslosoft\ApiFacades\Base\GenericClient;

class TestingClient extends GenericClient
{
	public array $lastRequest = [];
	public string $response = '[]';

	protected function request(string $url, string $method, array $headers, ?string $body): string
	{
		$this->lastRequest = [
			'url' => $url,
			'method' => $method,
			'headers' => $headers,
			'body' => $body,
		];

		return $this->response;
	}
}
