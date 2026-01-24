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
		$path = __DIR__ . '/data';
		$builder = new Builder(Config::load("$path/api-facades.01.yml"));
		$this->assertMethodExists($builder, 'build');
		$builder->build();

		$this->assertDirectoryExists("$path/generated.01");
		$this->assertFileExists("$path/generated/Client01.php");

		$this->markTestIncomplete("This test has not been finished yet.");
    }
}
