<?php

namespace Maslosoft\ApiFacades\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

function log(string $message, Level $level = Level::Debug, $context = []): void
{
	static $logger = null;
	if ($logger === null)
	{
		$logger = new Logger('api-facades');
		$logger->pushHandler(new StreamHandler('php://stdout'));
	}
	$logger->{$level->getName()}($message);
}

function debug(string $message, $context = []): void
{
	log($message, Level::Debug, $context);
}

function info(string $message, $context = []): void
{
	log($message, Level::Info, $context);
}

function warn(string $message, $context = []): void
{
	log($message, Level::Warning, $context);
}

function error(string $message, $context = []): void
{
	log($message, Level::Error, $context);
}