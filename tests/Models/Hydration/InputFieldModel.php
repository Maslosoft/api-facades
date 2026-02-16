<?php

namespace Tests\Models\Hydration;

use Maslosoft\ApiFacades\Hydrators\Attributes\InputField;

class InputFieldModel
{
	#[InputField('user_name')]
	public string $userName = 'default';

	public string $status = '';
}
