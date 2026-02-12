<?php

namespace Tests\Unit\Clients;

use Maslosoft\ApiFacades\Exceptions\BadParamsException;
use Tests\Models\Clients\TestingClient;
use Tests\Support\Unit;

class GenericClientTest extends Unit
{
	protected function _before(): void
	{
		$this->includeAll(dirname(__DIR__, 2) . '/Models');
	}

	public function testBuildsUrlWithPathAndQueryParams(): void
	{
		$client = new TestingClient();
		$client->baseUrl = 'https://api.example.com/';
		$client->response = '{"ok":true}';

		$data = $client->getData('/api/users/{id}', 'get', [
			'id' => 5,
			'include' => 'roles',
			'page' => 2,
		]);

		$this->assertSame(['ok' => true], $data);
		$this->assertSame('GET', $client->lastRequest['method']);

		$url = $client->lastRequest['url'];
		$this->assertSame('/api/users/5', parse_url($url, PHP_URL_PATH));

		parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
		$this->assertSame(['include' => 'roles', 'page' => '2'], $query);
		$this->assertContains('Accept: application/json', $client->lastRequest['headers']);
		$this->assertNotContains('Content-Type: application/json', $client->lastRequest['headers']);
	}

	public function testEncodesJsonBodyForWriteMethods(): void
	{
		$client = new TestingClient();
		$client->baseUrl = 'https://api.example.com';
		$client->response = '{"status":"ok"}';

		$data = $client->getData('api/users', 'post', [], ['name' => 'Ada']);

		$this->assertSame(['status' => 'ok'], $data);
		$this->assertSame('POST', $client->lastRequest['method']);
		$this->assertSame('{"name":"Ada"}', $client->lastRequest['body']);
		$this->assertContains('Content-Type: application/json', $client->lastRequest['headers']);
	}

	public function testMissingPathParamThrows(): void
	{
		$client = new TestingClient();
		$client->baseUrl = 'https://api.example.com';

		$this->expectException(BadParamsException::class);

		$client->getData('/api/users/{id}', 'get');
	}
}
