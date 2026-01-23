<?php

namespace Maslosoft\ApiFacades\Processors\Tags;

use Maslosoft\ApiFacades\Interfaces\Processor;

class FirstTag
implements Processor
{
	public function process($value)
	{
		if(is_array($value))
		{
			$first = reset($value);
			return is_string($first) ? $first : '';
		}
		return is_string($value) ? $value : '';
	}

}
