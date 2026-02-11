<?php

namespace Tests\Support;

use Codeception\Test\Unit as CodeceptionUnit;
use Maslosoft\Testing\Reflection\ReflectionAssertionsTrait;

class Unit extends CodeceptionUnit
{
	use ReflectionAssertionsTrait;

	/**
	 * Include once all files in path
	 * @param string $path
	 * @return void
	 */
	public function includeAll(string $path): void
	{
		foreach (glob($path . '/*.php') as $file)
		{
			require_once $file;
		}
	}
}
