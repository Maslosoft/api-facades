<?php

declare(strict_types=1);

namespace Maslosoft\ApiFacades\Models\Generic;

use Psr\Http\Message\StreamInterface;

/**
 * Generic multipart upload wrapper modeled after FastAPI/Starlette UploadFile.
 */
final class UploadFile
{
	public mixed $file;

	public string $filename;

	/**
	 * @var array<string, string>
	 */
	public array $headers;

	public ?int $size;

	/**
	 * @param array<string, string> $headers
	 */
	public function __construct(mixed $file, string $filename, array $headers = [], ?int $size = null)
	{
		$filename = trim($filename);
		if ($filename === '')
		{
			throw new \InvalidArgumentException('Upload filename cannot be empty.');
		}

		$this->file = $file;
		$this->filename = $filename;
		$this->headers = $headers;
		$this->size = $size ?? self::detectSize($file);
	}

	/**
	 * @param array<string, string> $headers
	 */
	public static function create(mixed $file, string $name, ?string $contentType = null, array $headers = []): self
	{
		if ($contentType !== null && $contentType !== '' && !self::hasHeader($headers, 'Content-Type'))
		{
			$headers['Content-Type'] = $contentType;
		}

		return new self($file, $name, $headers);
	}

	public function getContentType(): ?string
	{
		foreach ($this->headers as $name => $value)
		{
			if (strtolower($name) === 'content-type')
			{
				return $value;
			}
		}

		return null;
	}

	/**
	 * @param array<string, string> $headers
	 */
	private static function hasHeader(array $headers, string $name): bool
	{
		$name = strtolower($name);
		foreach ($headers as $headerName => $_value)
		{
			if (strtolower($headerName) === $name)
			{
				return true;
			}
		}

		return false;
	}

	private static function detectSize(mixed $file): ?int
	{
		if (is_string($file))
		{
			return strlen($file);
		}
		if ($file instanceof StreamInterface)
		{
			return $file->getSize();
		}
		if (is_resource($file))
		{
			$stats = fstat($file);
			return isset($stats['size']) && is_int($stats['size']) ? $stats['size'] : null;
		}
		if ($file instanceof \Stringable)
		{
			return strlen((string)$file);
		}

		return null;
	}
}
