<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Exceptions\Http;

use RuntimeException;
use Throwable;

class HttpException extends RuntimeException
{
	/**
	 * @param array<string, list<string>> $responseHeaders
	 */
	public function __construct(
		string $message,
		private readonly int $statusCode,
		private readonly string $method,
		private readonly string $url,
		private readonly array $responseHeaders = [],
		private readonly string $responseBody = '',
		private readonly string $reasonPhrase = '',
		?Throwable $previous = null
	)
	{
		parent::__construct($message, $statusCode, $previous);
	}

	public function getStatusCode(): int
	{
		return $this->statusCode;
	}

	public function getMethod(): string
	{
		return $this->method;
	}

	public function getUrl(): string
	{
		return $this->url;
	}

	/**
	 * @return array<string, list<string>>
	 */
	public function getResponseHeaders(): array
	{
		return $this->responseHeaders;
	}

	public function getResponseBody(): string
	{
		return $this->responseBody;
	}

	public function getReasonPhrase(): string
	{
		return $this->reasonPhrase;
	}
}
