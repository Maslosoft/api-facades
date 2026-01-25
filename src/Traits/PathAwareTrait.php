<?php

namespace Maslosoft\ApiFacades\Traits;

trait PathAwareTrait
{
	private string $path;

	public function setPath(string $path): static
	{
		$this->path = $path;
		return $this;
	}

	public function getPath(): string
	{
		return $this->path;
	}
}