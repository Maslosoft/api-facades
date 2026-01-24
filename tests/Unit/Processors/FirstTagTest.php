<?php

namespace Tests\Unit\Processors;

use Codeception\Test\Unit;
use Maslosoft\ApiFacades\Processors\Tags\FirstTag;

class FirstTagTest extends Unit
{
	public function testReturnsFirstTag(): void
	{
		$processor = new FirstTag();
		$processor->setTags(['admin', 'tenant']);
		$this->assertSame('admin', $processor->process(''));
	}

	public function testReturnsEmptyWhenNoTags(): void
	{
		$processor = new FirstTag();
		$this->assertSame('', $processor->process([]));
	}
}
