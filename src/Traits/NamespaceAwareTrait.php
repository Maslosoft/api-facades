<?php

namespace Maslosoft\ApiFacades\Traits;

trait NamespaceAwareTrait
{
	public string $namespace;

	public function setNamespace(string $namespace): static
	{
		$this->namespace = $namespace;
		return $this;
	}

	public function getNamespace(): string
	{
		return $this->namespace;
	}
}