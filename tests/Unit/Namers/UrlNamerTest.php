<?php

namespace Tests\Unit\Namers;

use Codeception\Test\Unit;
use Maslosoft\ApiFacades\Namers\Module\UrlNamer;
use Maslosoft\ApiFacades\Processors\Tags\FirstTag;
use Maslosoft\ApiFacades\Processors\Url\TrimPrefix;

class UrlNamerTest extends Unit
{
	public function testUsesUrlPathByDefault(): void
	{
		$namer = new UrlNamer('/api/admin/runs', ['admin'], new TrimPrefix('api'), new FirstTag());
		$this->assertSame('admin', $namer->getName());
	}

	public function testFallsBackToTagWhenPathEmpty(): void
	{
		$namer = new UrlNamer('/', ['tenant'], new TrimPrefix('api'), new FirstTag());
		$this->assertSame('tenant', $namer->getName());
	}
}
