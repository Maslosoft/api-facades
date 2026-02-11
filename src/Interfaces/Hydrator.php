<?php

namespace Maslosoft\ApiFacades\Interfaces;

/**
 * @template T of object
 */
interface Hydrator
{
	/**
	 * @param T $object
	 * @param array $data
	 * @return T
	 */
	public function hydrate(object $object, array $data): object;
}
