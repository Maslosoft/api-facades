<?php

namespace Models\Hydration;

use Maslosoft\ApiFacades\Hydrators\Casts\Scalar;
use Maslosoft\ApiFacades\Hydrators\Casts\ScalarArray;

class ScalarArrayModel
{
	#[ScalarArray(Scalar::int)]
	public array $values = [];
}
