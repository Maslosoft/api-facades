<?php

namespace Maslosoft\ApiFacades\Namers\Module;

use Maslosoft\ApiFacades\Interfaces\Processor;
use Maslosoft\ApiFacades\Interfaces\ModuleNamer;
use Maslosoft\ApiFacades\Interfaces\TagsAware;

class UrlNamer implements ModuleNamer
{
	/**
	 * @var Processor[]
	 */
	public array $processors = [];

	private string $path;

	/** @var string[] */
	private array $tags;

	/**
	 * @param string   $path
	 * @param string[] $tags
	 */
	public function __construct(
		string               $path,
		array                $tags = [],
		null|array|Processor $processors = null
	)
	{
		$this->path = $path;
		$this->tags = $tags;
		if ($processors instanceof Processor)
		{
			$this->processors[] = $processors;
		}
		else
		{
			$this->processors = $processors;
		}
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
			$path = $processor->process($path);
			if(empty($path))
			{
				continue;
			}
			return $path;
		}
		return '';
	}

}
