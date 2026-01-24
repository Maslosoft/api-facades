<?php

namespace Maslosoft\ApiFacades\Interfaces;

interface NameAware
{
	public function setName(string $name): static;
}