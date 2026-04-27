<?php

namespace Unit\Generate;

use Maslosoft\ApiFacades\Build\Builder;
use Maslosoft\ApiFacades\Config;
use Tests\Support\Unit;
use function bin2hex;
use function file_put_contents;
use function json_encode;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

class FullBuilderTest extends Unit
{
	public function testFullBuilder(): void
	{
		$confDir = __DIR__ . '/data';

		$openApiPath = $confDir . '/openapi.03.json';
		$configPath = $confDir . '/api-facades.03.yml';

		$builder = new Builder(Config::load($configPath));
		$builder->build();

		$client = new \Acme\Test03\Client03();

		// TODO: Should allow param-less and request /api/invoices/
		$client->invoices->get();

		// TODO: Missing verb
		$client->invoices->delete($invoiceId);
	}
}
