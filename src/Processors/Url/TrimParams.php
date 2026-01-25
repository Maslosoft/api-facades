<?php

namespace Maslosoft\ApiFacades\Processors\Url;

use Maslosoft\ApiFacades\Interfaces\Processor;

class TrimParams implements Processor
{
	public function process($value)
	{
		if(!str_contains($value, '{'))
		{
			return $value;
		}
		$value = preg_replace('~\{.*?}~', '', $value);
		return rtrim($value, '/');
	}

}
