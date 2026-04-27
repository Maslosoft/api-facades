<?php

declare(strict_types=1);

namespace Tests\Unit\Generate;

use FilesystemIterator;
use Maslosoft\ApiFacades\Build\Builder;
use Maslosoft\ApiFacades\Config;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use Tests\Support\Unit;

class FullBuilderTest extends Unit
{
	public function testFullBuilder(): void
	{
		$confDir = __DIR__ . '/data';
		$configPath = $confDir . '/api-facades.03.yml';
		$generatedPath = $confDir . '/generated.03';

		$builder = new Builder(Config::load($configPath));
		$builder->build();

		$this->assertFileExists($generatedPath . '/Modules/InvoicesModule.php');
		$this->assertFileExists($generatedPath . '/ModuleVerbs/InvoicesVerb.php');

		$this->requirePhpFiles($generatedPath);

		$client = new class extends \Acme\Test03\Client03
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
					'/api/invoices/|GET' => '{"mode":"collection"}',
					'/api/invoices/1|GET' => '{"id":1,"filename":"invoice-1.pdf","upload_date":"2026-01-01","status":"new"}',
					'/api/invoices/1|DELETE' => '{"deleted":1}',
					default => throw new \RuntimeException("Unexpected request {$method} {$path}."),
				};
			}
		};
		$client->baseUrl = 'https://api.example.test';

		$this->assertSame(['mode' => 'collection'], $client->invoices->get());
		$this->assertSame(['mode' => 'collection'], $client->invoices->get(skip: 10));
		$this->assertStringContainsString('/api/invoices/?skip=10', $client->captured['url']);

		$detail = $client->invoices->get(1);
		$this->assertInstanceOf(\Acme\Test03\Models\InvoiceDetailResponse::class, $detail);
		$this->assertSame(1, $detail->id);
		$this->assertSame('invoice-1.pdf', $detail->filename);
		$this->assertSame(['deleted' => 1], $client->invoices->delete(1));

		$getMethod = new ReflectionMethod(\Acme\Test03\Modules\InvoicesModule::class, 'get');
		$getParameters = $getMethod->getParameters();
		$this->assertCount(16, $getParameters);
		$this->assertSame('invoiceId', $getParameters[0]->getName());
		$this->assertTrue($getParameters[0]->isDefaultValueAvailable());
		$this->assertNull($getParameters[0]->getDefaultValue());
		$this->assertTrue(method_exists(\Acme\Test03\Modules\InvoicesModule::class, 'delete'));
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
