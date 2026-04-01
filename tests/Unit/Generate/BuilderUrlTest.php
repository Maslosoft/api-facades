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
use ReflectionProperty;
use Tests\Support\Unit;

class BuilderUrlTest extends Unit
{
	public function testBuildsTypedFacadeFromExampleConfig(): void
	{
		$dataPath = __DIR__ . '/data';
		$generatedPath = $dataPath . '/generated.01';

		$builder = new Builder(Config::load($dataPath . '/api-facades.01.yml'));
		$builder->build();

		$this->assertFileExists($generatedPath . '/Client01.php');
		$this->assertFileExists($generatedPath . '/Modules/AdminModule.php');
		$this->assertFileExists($generatedPath . '/Modules/TenantModule.php');
		$this->assertFileExists($generatedPath . '/Modules/Admin/TenantModule.php');
		$this->assertFileExists($generatedPath . '/Verbs/Admin/RunVerb.php');
		$this->assertFileExists($generatedPath . '/Verbs/Admin/RunsVerb.php');
		$this->assertFileExists($generatedPath . '/Verbs/Tenant/ProfileVerb.php');
		$this->assertFileExists($generatedPath . '/Models/AckResponse.php');
		$this->assertFileExists($generatedPath . '/Models/Tenant.php');
		$this->assertFileDoesNotExist($generatedPath . '/Models/HTTPValidationError.php');

		$this->requirePhpFiles($generatedPath);

		$client = new class extends \Acme\Test01\Client01
		{
			protected function request(string $url, string $method, array $headers, ?string $body): string
			{
				$path = (string)parse_url($url, PHP_URL_PATH);

				return match ($path . '|' . $method)
				{
					'/api/admin/run/5|GET' => '{"id":5,"name":"Example","email":"tenant@example.test","type":"real","active":true,"blocked":false}',
					'/api/admin/run/5|DELETE' => '{"status":"ok","message":"deleted"}',
					'/api/admin/tenant/block/3|POST' => '{"status":"ok","message":"blocked"}',
					'/api/admin/runs|GET' => '{"current":"run-1"}',
					'/api/tenant/profile|GET' => '{"company":"Acme"}',
					'/api/tenant/runs|GET' => '[{"id":1}]',
					default => throw new \RuntimeException("Unexpected request {$method} {$path}."),
				};
			}
		};
		$client->baseUrl = 'https://api.example.test';

		$this->assertInstanceOf(\Acme\Test01\Modules\AdminModule::class, $client->admin);
		$this->assertInstanceOf(\Acme\Test01\Modules\TenantModule::class, $client->tenant);
		$this->assertInstanceOf(\Acme\Test01\Modules\Admin\TenantModule::class, $client->admin->tenant);

		$tenant = $client->admin->run->get(5);
		$this->assertInstanceOf(\Acme\Test01\Models\Tenant::class, $tenant);
		$this->assertSame(5, $tenant->id);
		$this->assertSame('Example', $tenant->name);
		$this->assertSame('tenant@example.test', $tenant->email);
		$this->assertTrue($tenant->active);
		$this->assertFalse($tenant->blocked);

		$delete = $client->admin->run->delete(5);
		$this->assertInstanceOf(\Acme\Test01\Models\AckResponse::class, $delete);
		$this->assertSame('ok', $delete->status);
		$this->assertSame('deleted', $delete->message);

		$block = $client->admin->tenant->block->post(3);
		$this->assertInstanceOf(\Acme\Test01\Models\AckResponse::class, $block);
		$this->assertSame('blocked', $block->message);

		$this->assertSame(['current' => 'run-1'], $client->admin->runs->get());
		$this->assertSame(['company' => 'Acme'], $client->tenant->profile->get());
		$this->assertSame([['id' => 1]], $client->tenant->runs->get());

		$runGet = new ReflectionMethod(\Acme\Test01\Verbs\Admin\RunVerb::class, 'get');
		$runGetReturn = $runGet->getReturnType();
		$this->assertInstanceOf(ReflectionNamedType::class, $runGetReturn);
		$this->assertSame(\Acme\Test01\Models\Tenant::class, $runGetReturn->getName());
		$this->assertCount(1, $runGet->getParameters());
		$this->assertSame('int', $runGet->getParameters()[0]->getType()?->getName());
		$this->assertFalse(method_exists(\Acme\Test01\Verbs\Admin\RunVerb::class, 'post'));

		$tenantId = new ReflectionProperty(\Acme\Test01\Models\Tenant::class, 'id');
		$this->assertSame('int', $tenantId->getType()?->getName());
		$ackStatus = new \Acme\Test01\Models\AckResponse();
		$this->assertSame('ok', $ackStatus->status);
		$this->assertSame('', $ackStatus->message);
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
