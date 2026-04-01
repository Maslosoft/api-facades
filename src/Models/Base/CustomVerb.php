<?php

namespace Maslosoft\ApiFacades\Models\Base;

use Maslosoft\ApiFacades\Base\GenericClient;

class CustomVerb
{
	protected GenericClient $client;

	public function __construct(GenericClient $client)
	{
		$this->client = $client;
	}
}
