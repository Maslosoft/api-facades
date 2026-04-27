<?php

declare(strict_types=1);

namespace Tests\Unit\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Maslosoft\ApiFacades\Base\GenericClient;
use Maslosoft\ApiFacades\Exceptions\BadParamsException;
use Maslosoft\ApiFacades\Exceptions\Http\TransportException;
use Maslosoft\ApiFacades\Exceptions\Http\UnauthorizedException;
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

	public function testSendsCustomHeaders(): void
	{
		$client = new TestingClient();
		$client->baseUrl = 'https://api.example.com';
		$client->response = '{"ok":true}';

		$data = $client->getData('/api/users', 'get', [], [], [
			'X-Api-Key' => 'secret',
			'X-Trace-Id' => 'abc-123',
		]);

		$this->assertSame(['ok' => true], $data);
		$this->assertContains('X-Api-Key: secret', $client->lastRequest['headers']);
		$this->assertContains('X-Trace-Id: abc-123', $client->lastRequest['headers']);
	}

	public function testMergesHeadersFromOverride(): void
	{
		$client = new class extends TestingClient
		{
			protected function getHeaders(): array
			{
				return [
					'Authorization' => 'Bearer token',
				];
			}
		};
		$client->baseUrl = 'https://api.example.com';
		$client->response = '{"ok":true}';

		$data = $client->getData('/api/users', 'get', [], [], [
			'X-Trace-Id' => 'abc-123',
		]);

		$this->assertSame(['ok' => true], $data);
		$this->assertContains('Authorization: Bearer token', $client->lastRequest['headers']);
		$this->assertContains('X-Trace-Id: abc-123', $client->lastRequest['headers']);
	}

	public function testUnauthorizedResponseThrowsSpecificException(): void
	{
		$client = $this->createHttpClient([
			new Response(401, ['Content-Type' => 'application/json'], '{"detail":"Unauthorized"}'),
		]);
		$client->baseUrl = 'https://api.example.com';

		try
		{
			$client->getData('/api/secure', 'get');
			$this->fail('Expected UnauthorizedException to be thrown.');
		}
		catch (UnauthorizedException $exception)
		{
			$this->assertSame(401, $exception->getStatusCode());
			$this->assertSame('GET', $exception->getMethod());
			$this->assertSame('https://api.example.com/api/secure', $exception->getUrl());
			$this->assertSame('{"detail":"Unauthorized"}', $exception->getResponseBody());
			$this->assertStringContainsString('HTTP 401', $exception->getMessage());
			$this->assertStringContainsString('Unauthorized', $exception->getMessage());
		}
	}

	public function testTransportErrorsAreWrapped(): void
	{
		$client = $this->createHttpClient([
			new ConnectException(
				'Connection refused',
				new Request('GET', 'https://api.example.com/api/secure')
			),
		]);
		$client->baseUrl = 'https://api.example.com';

		try
		{
			$client->getData('/api/secure', 'get');
			$this->fail('Expected TransportException to be thrown.');
		}
		catch (TransportException $exception)
		{
			$this->assertSame('GET', $exception->getMethod());
			$this->assertSame('https://api.example.com/api/secure', $exception->getUrl());
			$this->assertStringContainsString('Connection refused', $exception->getMessage());
		}
	}

	/**
	 * @param array<int, Response|\Throwable> $queue
	 */
	private function createHttpClient(array $queue): GenericClient
	{
		$mock = new MockHandler($queue);
		$guzzle = new Client([
			'handler' => HandlerStack::create($mock),
		]);

		$client = new class extends GenericClient
		{
		};
		$client->setHttpClient($guzzle);

		return $client;
	}
}
