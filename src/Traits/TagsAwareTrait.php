<?php

namespace Maslosoft\ApiFacades\Traits;

trait TagsAwareTrait
{
	/**
	 * @var string[]
	 */
	public array $tags = [];

	public function getTags(): array
	{
		return $this->tags;
	}

	public function setTags(array $tags): static
	{
		$this->tags = $tags;
		return $this;
	}
}