<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Console;

use Symfony\Component\Console\Application;

final class ApplicationFactory
{
	public static function create(): Application
	{
		$application = new Application('api-facades');
		$application->add(new BuildCommand());
		$application->setDefaultCommand(BuildCommand::Name, true);

		return $application;
	}
}
