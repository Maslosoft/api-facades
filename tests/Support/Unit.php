<?php

namespace Tests\Support;

use Codeception\Test\Unit as CodeceptionUnit;
use Maslosoft\Testing\Reflection\ReflectionAssertionsTrait;

class Unit extends CodeceptionUnit
{
	use ReflectionAssertionsTrait;
}