<?php

namespace Maslosoft\ApiFacades\Hydrators\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class InputField
{
	public string $name;

	public function __construct(string $name)
	{
		$this->name = $name;
	}
}
