<?php

namespace Maslosoft\ApiFacades\Processors\Url;

use Maslosoft\ApiFacades\Interfaces\Processor;

class TrimPrefix
implements Processor
{
	private string $prefix;

	public function __construct(string $prefix = '')
	{
		$this->prefix = $prefix;
	}

	public function process($value)
	{
		if(!is_string($value))
		{
			return $value;
		}
		$path = trim($value);
		if($path === '')
		{
			return $path;
		}
		if(strpos($path, '://') !== false)
		{
			$parsed = parse_url($path);
			if(is_array($parsed) && isset($parsed['path']))
			{
				$path = (string)$parsed['path'];
			}
		}
		$path = trim($path, '/');
		$prefix = trim($this->prefix, '/');
		if($prefix !== '')
		{
			$prefixWithSlash = $prefix . '/';
			if(strncmp($path, $prefixWithSlash, strlen($prefixWithSlash)) === 0)
			{
				$path = substr($path, strlen($prefixWithSlash));
			}
			elseif($path === $prefix)
			{
				$path = '';
			}
		}
		return trim($path, '/');
	}

}
