<?php

namespace Tests\Unit\Processors;

use Codeception\Test\Unit;
use Maslosoft\ApiFacades\Processors\Url\TrimPrefix;

class TrimPrefixTest extends Unit
{
	public function testTrimsPrefixFromPath(): void
	{
		$processor = new TrimPrefix('api');
		$this->assertSame('admin/runs', $processor->process('/api/admin/runs'));
	}

	public function testTrimsPrefixFromUrl(): void
	{
		$processor = new TrimPrefix('api');
		$this->assertSame('tenant/profile', $processor->process('https://example.test/api/tenant/profile'));
	}
}
