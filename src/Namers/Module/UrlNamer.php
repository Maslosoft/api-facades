<?php

namespace Maslosoft\ApiFacades\Namers\Module;

use Maslosoft\ApiFacades\Interfaces\PathAware;
use Maslosoft\ApiFacades\Interfaces\Processor;
use Maslosoft\ApiFacades\Interfaces\ModuleNamer;
use Maslosoft\ApiFacades\Interfaces\TagsAware;
use Maslosoft\ApiFacades\Traits\PathAwareTrait;
use Maslosoft\ApiFacades\Traits\TagsAwareTrait;

class UrlNamer implements ModuleNamer, PathAware, TagsAware
{
	use PathAwareTrait,
		TagsAwareTrait;

	/**
	 * @var Processor[]
	 */
	public array $processors = [];

	/**
	 * @param string $path
	 * @param string[] $tags
	 * @param Processor[] $processors
	 */
	public function __construct(string $path = '', array $tags = [], array $processors = [])
	{
		$this->path = $path;
		$this->tags = $tags;
		$this->processors = $processors;
	}

	public function getName(): string
	{
		$path = $this->path;
		foreach($this->processors as $processor)
		{
			if($processor instanceof TagsAware)
			{
				$processor->setTags($this->tags);
			}
			$processed = $processor->process($path);
			if($processed === null)
			{
				continue;
			}
			$path = (string)$processed;
			if($path === '')
			{
				continue;
			}
		}
		return $path;
	}

}
