<?php

namespace Maslosoft\ApiFacades\Namers\Module;

use Maslosoft\ApiFacades\Interfaces\Processor;
use Maslosoft\ApiFacades\Interfaces\ModuleNamer;

class UrlNamer implements ModuleNamer
{
	private string $path;
	/** @var string[] */
	private array $tags;
	private ?Processor $urlProcessor;
	private ?Processor $tagProcessor;

	/**
	 * @param string $path
	 * @param string[] $tags
	 */
	public function __construct(
		string $path,
		array $tags = [],
		?Processor $urlProcessor = null,
		?Processor $tagProcessor = null
	)
	{
		$this->path = $path;
		$this->tags = $tags;
		$this->urlProcessor = $urlProcessor;
		$this->tagProcessor = $tagProcessor;
	}

	public function getName(): string
	{
		$path = $this->path;
		if($this->urlProcessor)
		{
			$path = $this->urlProcessor->process($path);
		}
		$path = is_string($path) ? trim($path, '/') : '';
		if($path !== '')
		{
			$parts = array_values(array_filter(explode('/', $path), 'strlen'));
			if(isset($parts[0]))
			{
				return $parts[0];
			}
		}
		$tags = $this->tags;
		if($this->tagProcessor)
		{
			$tag = $this->tagProcessor->process($tags);
			return is_string($tag) ? $tag : '';
		}
		$tag = reset($tags);
		return is_string($tag) ? $tag : '';
	}

}
