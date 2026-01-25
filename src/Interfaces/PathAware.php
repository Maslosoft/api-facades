<?php

namespace Maslosoft\ApiFacades\Interfaces;

interface PathAware
{
	public function setPath(string $path): static;
}