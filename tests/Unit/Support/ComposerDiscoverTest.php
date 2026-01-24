<?php


namespace Tests\Unit\Support;

use Maslosoft\ApiFacades\Config;
use Maslosoft\ApiFacades\Support\ComposerDiscover;
use Tests\Support\UnitTester;

class ComposerDiscoverTest extends \Codeception\Test\Unit
{

    protected UnitTester $tester;

    protected function _before()
    {
    }

    // tests
    public function testDiscoveringThisProjectRoot(): void
	{
		$root = realpath(__DIR__.'/../../../');
		$ns = "Maslosoft\ApiFacades\Api\Test";
		$this->assertDirectoryExists($root, "Root of this project directory does not exist, possibly misallocated test [" . __CLASS__ . "]");
		$config = Config::load(__DIR__ . "/data/api-facades.01.yml");
		$config->output->namespace = $ns;
		$discoverer = new ComposerDiscover($config);
		$path = $discoverer->discover($ns);
		codecept_debug($path);
		$this->assertSame("$root/src/Api/Test", $path);
    }
}
