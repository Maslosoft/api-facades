<?php

namespace Maslosoft\ApiFacades\Processors\Url;


use Maslosoft\ApiFacades\Interfaces\Processor;

/**
 * Drop last part of the path.
 *
 * Example:
 *
 * admin/tenants/remove -> admin/tenants
 *
 */
class DropLastPart implements Processor
{
	public function process($value)
	{
		$parts = explode('/', $value);
		if (count($parts) < 2)
		{
			return $value;
		}
		array_pop($parts);
		return implode('/', $parts);
	}

}