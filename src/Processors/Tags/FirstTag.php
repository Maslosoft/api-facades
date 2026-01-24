<?php

namespace Maslosoft\ApiFacades\Processors\Tags;

use Maslosoft\ApiFacades\Interfaces\Processor;
use Maslosoft\ApiFacades\Interfaces\TagsAware;
use Maslosoft\ApiFacades\Traits\TagsAwareTrait;

class FirstTag implements Processor, TagsAware
{
	use TagsAwareTrait;

	public function process($value)
	{
		if(empty($this->tags))
		{
			return '';
		}
		if(is_array($value))
		{
			$first = reset($value);
			$value = is_string($first) ? $first : '';
		}
		$value = is_string($value) ? $value : '';
		reset($this->tags);
		return current($this->tags);
	}

}
