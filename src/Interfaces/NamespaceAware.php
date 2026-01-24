<?php

namespace Maslosoft\ApiFacades\Interfaces;

interface NamespaceAware
{
	public function setNamespace(string $namespace): static;
}