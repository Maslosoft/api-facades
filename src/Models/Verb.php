<?php

namespace Maslosoft\ApiFacades\Models;

use Maslosoft\ApiFacades\Exceptions\BadVerbException;

/**
 * @template TGet
 * @template TPost
 * @template TPut
 * @template TDelete
 * @template TPatch
 */
final class Verb
{
	/** @var array<string, callable> */
	private array $verbs;
	private string $owner;
	private string $method;

	public function __construct(
		array  $verbs,
		string $owner,
		string $method
	)
	{
		$this->method = $method;
		$this->owner = $owner;
		$this->verbs = $verbs;
	}

	/** @return TGet */
	public function get(...$arguments)
	{
		/** @var TGet */
		return $this->call('get', ...$arguments);
	}

	/** @return TPost */
	public function post(...$arguments)
	{
		/** @var TPost */
		return $this->call('post', ...$arguments);
	}

	/** @return TPut */
	public function put(...$arguments)
	{
		/** @var TPut */
		return $this->call('put', ...$arguments);
	}

	/** @return TDelete */
	public function delete(...$arguments)
	{
		/** @var TDelete */
		return $this->call('delete', ...$arguments);
	}

	/** @return TPatch */
	public function patch(...$arguments)
	{
		/** @var TPatch */
		return $this->call('patch', ...$arguments);
	}

	private function call(string $verb, ...$arguments)
	{
		if(!array_key_exists($verb, $this->verbs))
		{
			throw new BadVerbException("{$this->owner}::{$this->method}() does not support verb '{$verb}'.");
		}
		return ($this->verbs[$verb])(...$arguments);
	}
}
