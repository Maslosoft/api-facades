<?php

namespace Tests\Models\Hydration;

use Maslosoft\ApiFacades\Hydrators\Casts\CastArray;

class ArrayCastModel
{
	#[CastArray(NestedModel::class)]
	public array $items = [];
}
