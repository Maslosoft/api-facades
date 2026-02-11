<?php

namespace Tests\Models\Hydration;

use Maslosoft\ApiFacades\Hydrators\Casts\Scalar;

class UnionScalarModel
{
	#[Scalar(Scalar::string)]
	public int|string $value;
}
