<?php

namespace Maslosoft\ApiFacades\Interfaces;

interface Hydrator
{
	public function hydrate(object $object, array $data): object;
}
