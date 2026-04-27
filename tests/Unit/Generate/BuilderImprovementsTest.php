<?php

declare(strict_types=1);

namespace Tests\Unit\Generate;

use FilesystemIterator;
use Maslosoft\ApiFacades\Build\Builder;
use Maslosoft\ApiFacades\Config;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\Support\Unit;

class BuilderImprovementsTest extends Unit
{
	public function testGeneratesModuleOwnedVerbsAndHeaderParameters(): void
	{
		$tempRoot = sys_get_temp_dir() . '/api-facades-improvements-' . bin2hex(random_bytes(6));
		mkdir($tempRoot, 0777, true);

		$openApiPath = $tempRoot . '/openapi.json';
		$configPath = $tempRoot . '/api-facades.yml';

		file_put_contents($openApiPath, json_encode($this->spec(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
		file_put_contents($configPath, $this->configYaml());

		$builder = new Builder(Config::load($configPath));
		$builder->build();

		$this->assertFileExists($tempRoot . '/generated/Client01.php');
		$this->assertFileExists($tempRoot . '/generated/Modules/InvoicesModule.php');
		$this->assertFileExists($tempRoot . '/generated/ModuleVerbs/InvoicesVerb.php');
		$this->assertFileExists($tempRoot . '/generated/Verbs/Invoices/ItemsVerb.php');
		$this->assertFileExists($tempRoot . '/generated/Verbs/UploadVerb.php');

		$this->requirePhpFiles($tempRoot . '/generated');

		$client = new class extends \Acme\Improvements01\Client01
		{
			public array $captured = [];

			protected function request(string $url, string $method, array $headers, ?string $body): string
			{
				$this->captured = [
					'url' => $url,
					'method' => $method,
					'headers' => $headers,
					'body' => $body,
				];

				$path = (string)parse_url($url, PHP_URL_PATH);
				return match ($path . '|' . $method)
				{
					'/api/invoices/|GET' => '{"source":"root"}',
					'/api/invoices/items|GET' => '["a","b"]',
					'/api/upload|POST' => '{"ok":true}',
					default => throw new \RuntimeException("Unexpected request {$method} {$path}."),
				};
			}
		};
		$client->baseUrl = 'https://api.example.test';

		$this->assertInstanceOf(\Acme\Improvements01\Modules\InvoicesModule::class, $client->invoices);
		$this->assertSame(['source' => 'root'], $client->invoices->get());
		$this->assertSame(['a', 'b'], $client->invoices->items->get());
		$this->assertSame(['ok' => true], $client->upload->post(['name' => 'Ada'], 'secret'));
		$this->assertContains('x-api-key: secret', $client->captured['headers']);
		$this->assertSame('{"name":"Ada"}', $client->captured['body']);

		$moduleMethod = new ReflectionMethod(\Acme\Improvements01\Modules\InvoicesModule::class, 'get');
		$this->assertSame('array', $moduleMethod->getReturnType()?->getName());

		$uploadMethod = new ReflectionMethod(\Acme\Improvements01\Verbs\UploadVerb::class, 'post');
		$parameters = $uploadMethod->getParameters();
		$this->assertCount(2, $parameters);
		$this->assertSame('body', $parameters[0]->getName());
		$this->assertSame('array', $parameters[0]->getType()?->getName());
		$this->assertSame('xApiKey', $parameters[1]->getName());
		$this->assertInstanceOf(ReflectionNamedType::class, $parameters[1]->getType());
		$this->assertSame('string', $parameters[1]->getType()?->getName());
		$this->assertTrue($parameters[1]->getType()?->allowsNull() ?? false);
		$this->assertTrue($parameters[1]->isDefaultValueAvailable());
		$this->assertNull($parameters[1]->getDefaultValue());
	}

	public function testDispatchesModuleOwnedCollectionAndDetailVariants(): void
	{
		$tempRoot = sys_get_temp_dir() . '/api-facades-improvements-' . bin2hex(random_bytes(6));
		mkdir($tempRoot, 0777, true);

		$openApiPath = $tempRoot . '/openapi.json';
		$configPath = $tempRoot . '/api-facades.yml';

		file_put_contents($openApiPath, json_encode($this->specWithOwnVerbVariants(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
		file_put_contents($configPath, $this->configYaml('Acme\\Improvements02', 'Client02'));

		$builder = new Builder(Config::load($configPath));
		$builder->build();

		$this->assertFileExists($tempRoot . '/generated/Modules/InvoicesModule.php');
		$this->assertFileExists($tempRoot . '/generated/ModuleVerbs/InvoicesVerb.php');

		$this->requirePhpFiles($tempRoot . '/generated');

		$client = new class extends \Acme\Improvements02\Client02
		{
			public array $captured = [];

			protected function request(string $url, string $method, array $headers, ?string $body): string
			{
				$this->captured = [
					'url' => $url,
					'method' => $method,
					'headers' => $headers,
					'body' => $body,
				];

				$path = (string)parse_url($url, PHP_URL_PATH);
				return match ($path . '|' . $method)
				{
					'/api/invoices/|GET' => '{"scope":"collection"}',
					'/api/invoices/7|GET' => '{"scope":"detail","id":7}',
					'/api/invoices/7|DELETE' => '{"deleted":true}',
					default => throw new \RuntimeException("Unexpected request {$method} {$path}."),
				};
			}
		};
		$client->baseUrl = 'https://api.example.test';

		$this->assertSame(['scope' => 'collection'], $client->invoices->get());
		$this->assertSame(['scope' => 'collection'], $client->invoices->get(skip: 5));
		$this->assertStringContainsString('/api/invoices/?skip=5', $client->captured['url']);
		$this->assertSame(['scope' => 'detail', 'id' => 7], $client->invoices->get(7));
		$this->assertSame(['deleted' => true], $client->invoices->delete(7));

		$getMethod = new ReflectionMethod(\Acme\Improvements02\Modules\InvoicesModule::class, 'get');
		$getParameters = $getMethod->getParameters();
		$this->assertSame('array', $getMethod->getReturnType()?->getName());
		$this->assertCount(2, $getParameters);
		$this->assertSame('invoiceId', $getParameters[0]->getName());
		$this->assertTrue($getParameters[0]->isDefaultValueAvailable());
		$this->assertNull($getParameters[0]->getDefaultValue());
		$this->assertSame('skip', $getParameters[1]->getName());
		$this->assertTrue(method_exists(\Acme\Improvements02\Modules\InvoicesModule::class, 'delete'));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function spec(): array
	{
		return [
			'openapi' => '3.1.0',
			'info' => [
				'title' => 'Improvements',
				'version' => '1.0.0',
			],
			'paths' => [
				'/api/invoices/' => [
					'get' => [
						'tags' => ['invoices'],
						'operationId' => 'get_invoices',
						'responses' => [
							'200' => [
								'description' => 'ok',
								'content' => [
									'application/json' => [
										'schema' => [
											'type' => 'object',
											'properties' => [
												'source' => ['type' => 'string'],
											],
											'required' => ['source'],
										],
									],
								],
							],
						],
					],
				],
				'/api/invoices/items' => [
					'get' => [
						'tags' => ['invoices'],
						'operationId' => 'get_invoice_items',
						'responses' => [
							'200' => [
								'description' => 'ok',
								'content' => [
									'application/json' => [
										'schema' => [
											'type' => 'array',
											'items' => ['type' => 'string'],
										],
									],
								],
							],
						],
					],
				],
				'/api/upload' => [
					'post' => [
						'tags' => ['upload'],
						'operationId' => 'upload_invoice',
						'parameters' => [
							[
								'name' => 'x-api-key',
								'in' => 'header',
								'required' => false,
								'schema' => [
									'anyOf' => [
										['type' => 'string'],
										['type' => 'null'],
									],
								],
							],
						],
						'requestBody' => [
							'required' => true,
							'content' => [
								'application/json' => [
									'schema' => [
										'type' => 'object',
										'properties' => [
											'name' => ['type' => 'string'],
										],
										'required' => ['name'],
									],
								],
							],
						],
						'responses' => [
							'200' => [
								'description' => 'ok',
								'content' => [
									'application/json' => [
										'schema' => [
											'type' => 'object',
											'properties' => [
												'ok' => ['type' => 'boolean'],
											],
											'required' => ['ok'],
										],
									],
								],
							],
						],
					],
				],
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function specWithOwnVerbVariants(): array
	{
		return [
			'openapi' => '3.1.0',
			'info' => [
				'title' => 'Own Verb Variants',
				'version' => '1.0.0',
			],
			'paths' => [
				'/api/invoices/' => [
					'get' => [
						'tags' => ['invoices'],
						'operationId' => 'list_invoices',
						'parameters' => [
							[
								'name' => 'skip',
								'in' => 'query',
								'required' => false,
								'schema' => [
									'type' => 'integer',
								],
							],
						],
						'responses' => [
							'200' => [
								'description' => 'ok',
								'content' => [
									'application/json' => [
										'schema' => [
											'type' => 'object',
											'properties' => [
												'scope' => ['type' => 'string'],
											],
											'required' => ['scope'],
										],
									],
								],
							],
						],
					],
				],
				'/api/invoices/{invoice_id}' => [
					'get' => [
						'tags' => ['invoices'],
						'operationId' => 'get_invoice',
						'parameters' => [
							[
								'name' => 'invoice_id',
								'in' => 'path',
								'required' => true,
								'schema' => [
									'type' => 'integer',
								],
							],
						],
						'responses' => [
							'200' => [
								'description' => 'ok',
								'content' => [
									'application/json' => [
										'schema' => [
											'type' => 'object',
											'properties' => [
												'scope' => ['type' => 'string'],
												'id' => ['type' => 'integer'],
											],
											'required' => ['scope', 'id'],
										],
									],
								],
							],
						],
					],
					'delete' => [
						'tags' => ['invoices'],
						'operationId' => 'delete_invoice',
						'parameters' => [
							[
								'name' => 'invoice_id',
								'in' => 'path',
								'required' => true,
								'schema' => [
									'type' => 'integer',
								],
							],
						],
						'responses' => [
							'200' => [
								'description' => 'ok',
								'content' => [
									'application/json' => [
										'schema' => [
											'type' => 'object',
											'properties' => [
												'deleted' => ['type' => 'boolean'],
											],
											'required' => ['deleted'],
										],
									],
								],
							],
						],
					],
				],
				'/api/invoices/history' => [
					'get' => [
						'tags' => ['invoices'],
						'operationId' => 'invoice_history',
						'responses' => [
							'200' => [
								'description' => 'ok',
								'content' => [
									'application/json' => [
										'schema' => [
											'type' => 'array',
											'items' => ['type' => 'string'],
										],
									],
								],
							],
						],
					],
				],
			],
		];
	}

	private function configYaml(string $namespace = 'Acme\\Improvements01', string $class = 'Client01'): string
	{
		return <<<YAML
jane:
  markInternal: true
input:
  location: openapi.json
generate:
  modules:
    namer:
      class: Maslosoft\ApiFacades\Namers\Module\UrlNamer
      processors:
        firstTag:
          class: Maslosoft\ApiFacades\Processors\Tags\FirstTag
        trimUrl:
          class: Maslosoft\ApiFacades\Processors\Url\TrimPrefix
          prefix: "api"
    baseClass: Maslosoft\ApiFacades\Base\GenericModule
  operations:
    namer:
      class: Maslosoft\ApiFacades\Namers\Operations\OperationId
    baseClass: Maslosoft\ApiFacades\Base\GenericOperation
output:
  namespace: {$namespace}
  class: {$class}
  discoverOutput: false
  path: "generated"
YAML;
	}

	private function requirePhpFiles(string $path): void
	{
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file)
		{
			if ($file->getExtension() !== 'php')
			{
				continue;
			}
			require_once $file->getPathname();
		}
	}
}
