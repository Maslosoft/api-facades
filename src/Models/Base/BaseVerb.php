<?php

namespace Maslosoft\ApiFacades\Models\Base;

use Maslosoft\ApiFacades\Base\GenericClient;
use function is_string;

abstract class BaseVerb
{
	/** @var array<string, callable> */
	protected array $verbs;
	protected GenericClient $client;

	protected string $method;

	public function __construct(
		array|string  $verbs,
		GenericClient $client,
		string        $method
	)
	{
		$this->method = $method;
		$this->client = $client;
		if(is_string($verbs))
		{
			$verbs = [$verbs];
		}
		$this->verbs = $verbs;
	}
}
