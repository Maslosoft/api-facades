<?php

namespace Tests\Unit\Namers;

use Codeception\Test\Unit;
use Maslosoft\ApiFacades\Namers\Operations\OperationId;

class OperationIdTest extends Unit
{
	public function testUsesLastSegmentOfOperationId(): void
	{
		$namer = new OperationId('admin.RunDelete');
		$this->assertSame('runDelete', $namer->getName());
	}

	public function testConvertsDelimitedOperationId(): void
	{
		$namer = new OperationId('health-check');
		$this->assertSame('healthCheck', $namer->getName());
	}
}
