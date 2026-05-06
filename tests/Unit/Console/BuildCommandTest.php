<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use FilesystemIterator;
use Maslosoft\ApiFacades\Console\BuildCommand;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\Unit;

class BuildCommandTest extends Unit
{
	public function testRunsBuilderFromCliArguments(): void
	{
		$fixturePath = dirname(__DIR__) . '/Generate/data';
		$tempRoot = sys_get_temp_dir() . '/api-facades-console-' . bin2hex(random_bytes(6));
		mkdir($tempRoot, 0777, true);

		$openApiPath = $tempRoot . '/openapi.json';
		$configPath = $tempRoot . '/api-facades.yml';

		copy($fixturePath . '/openapi.01.json', $openApiPath);

		$config = file_get_contents($fixturePath . '/api-facades.01.yml');
		$this->assertIsString($config);
		$config = str_replace('namespace: Acme\\Test01', 'namespace: Acme\\Console01', $config);
		$config = str_replace('path: "generated.01"', 'path: "generated-cli"', $config);
		file_put_contents($configPath, $config);

		$tester = new CommandTester(new BuildCommand());
		$status = $tester->execute([
			'openapi' => $openApiPath,
			'config' => $configPath,
		]);

		$this->assertSame(0, $status, $tester->getDisplay());
		$this->assertStringContainsString('Generated API facades', $tester->getDisplay());
		$this->assertFileExists($tempRoot . '/generated-cli/Client01.php');
		$this->assertFileExists($tempRoot . '/generated-cli/Modules/AdminModule.php');
		$this->assertFileExists($tempRoot . '/generated-cli/Verbs/Admin/RunVerb.php');

		$this->requirePhpFiles($tempRoot . '/generated-cli');
		$this->assertTrue(class_exists(\Acme\Console01\Client01::class));
	}

	private function requirePhpFiles(string $path): void
	{
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file)
		{
			if ($file->getExtension() !== 'php')
			{
				continue;
			}
			require_once $file->getPathname();
		}
	}
}
