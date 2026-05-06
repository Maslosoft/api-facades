<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Exceptions\Http;

use RuntimeException;
use Throwable;

class TransportException extends RuntimeException
{
	public function __construct(
		string $message,
		private readonly string $method,
		private readonly string $url,
		?Throwable $previous = null
	)
	{
		parent::__construct($message, 0, $previous);
	}

	public function getMethod(): string
	{
		return $this->method;
	}

	public function getUrl(): string
	{
		return $this->url;
	}
}
