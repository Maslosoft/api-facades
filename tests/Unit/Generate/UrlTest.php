<?php


namespace Tests\Unit\Generate;

use Codeception\Test\Unit;
use Maslosoft\ApiFacades\Build\Builder;
use Tests\Support\UnitTester;

class UrlTest extends Unit
{

    protected UnitTester $tester;

    protected function _before()
    {
    }

    // tests
    public function testGeneratingFromUrl(): void
    {
		$builder = new Builder();
    }
}
