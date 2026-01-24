<?php

namespace Maslosoft\ApiFacades\Traits;

trait NameAwareTrait
{
	public string $name;

	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}

	public function getName(): string
	{
		return $this->name;
	}
}