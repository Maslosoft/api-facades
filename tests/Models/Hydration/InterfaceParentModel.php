<?php

namespace Tests\Models\Hydration;

use Maslosoft\ApiFacades\Hydrators\Casts\Cast;

class InterfaceParentModel
{
	#[Cast(CastChild::class)]
	public NestedContract $child;
}
