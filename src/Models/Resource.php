<?php

namespace Maslosoft\ApiFacades\Models;

final class Resource
{
	/** @var array<string, Op> map verb => Op */
	public array $verbs = [];
	public string $name;
	public string $path;
	public string $tag;

	public function __construct(
		string $name,   // method name, e.g. 'run' or 'profile'
		string $path,
		string $tag
	)
	{
		$this->tag = $tag;
		$this->path = $path;
		$this->name = $name;
	}
}
