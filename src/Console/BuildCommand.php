<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Console;

use Maslosoft\ApiFacades\Build\Builder;
use Maslosoft\ApiFacades\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class BuildCommand extends Command
{
	public const string Name = 'build';

	protected static $defaultName = self::Name;

	protected static $defaultDescription = 'Generate typed PHP API facades from an OpenAPI JSON file and api-facades config.';

	protected function configure(): void
	{
		$this
			->setName(self::Name)
			->addArgument('openapi', InputArgument::REQUIRED, 'Path or URL to the OpenAPI JSON document.')
			->addArgument('config', InputArgument::REQUIRED, 'Path to the api-facades YAML or PHP config file.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$openApiPath = (string)$input->getArgument('openapi');
		$configPath = (string)$input->getArgument('config');

		$config = Config::load($configPath);
		$config->input->location = $this->resolvePath($openApiPath);

		$builder = new Builder($config);
		$builder->build();

		$io->success(sprintf(
			'Generated API facades from `%s` using `%s` into `%s`.',
			$config->input->location,
			$config->filename,
			$config->output->path
		));

		return Command::SUCCESS;
	}

	private function resolvePath(string $path): string
	{
		$path = trim($path);
		if ($path === '')
		{
			return $path;
		}

		$lowerPath = strtolower($path);
		if (str_starts_with($lowerPath, 'http://') || str_starts_with($lowerPath, 'https://'))
		{
			return $path;
		}
		if (str_starts_with($path, '/'))
		{
			return $path;
		}
		if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path))
		{
			return $path;
		}

		$realPath = realpath($path);
		if ($realPath !== false)
		{
			return $realPath;
		}

		$cwd = getcwd();
		if ($cwd === false || $cwd === '')
		{
			return $path;
		}

		return rtrim($cwd, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
	}
}
