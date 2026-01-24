<?php


namespace Tests\Unit\Generate;

use Maslosoft\ApiFacades\Build\Builder;
use Tests\Support\Unit;
use Tests\Support\UnitTester;
use Maslosoft\ApiFacades\Build\Config;

class UrlTest extends Unit
{

    protected UnitTester $tester;

    protected function _before()
    {
    }

    // tests
    public function testGeneratingFromUrl(): void
    {
		$builder = new Builder(Config::load(__DIR__ . '/data/api-facades.01.yml'));
		$this->assertMethodExists($builder, 'build');
		$this->markTestIncomplete("This test has not been implemented yet.");
    }
}
