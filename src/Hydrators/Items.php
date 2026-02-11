<?php

namespace Maslosoft\ApiFacades\Hydrators;

use Maslosoft\ApiFacades\Interfaces\Hydrator;

class Items
{
	/**
	 * Hydrate items with provided hydrator and item class from data. The data must contain relevant keys and values.
	 *
	 * @param Hydrator $hydrator
	 * @param string $class
	 * @param array $data
	 * @return array
	 */
	public static function hydrate(Hydrator $hydrator, string $class, array $data): array
	{
		$result = [];
		foreach ($data as $item)
		{
			$result[] = $hydrator->hydrate(new $class, $item);
		}
		return $result;
	}
}
