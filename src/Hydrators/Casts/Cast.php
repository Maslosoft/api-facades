<?php

namespace Maslosoft\ApiFacades\Hydrators\Casts;

use Attribute;

/**
 * Attribute for casting single value into an object. When using type definition it's not required or can be union type or interface.
 *
 * Usage:
 * ```php
 *     #[Cast(RevenueItem::class)]
 *     public RevenueItem $item;
 * ```
 *
 *  Not needed if type is defined, and it's only one type (union types are not supported):
 *  ```php
 *      public ?RevenueItem $item = null;
 *  ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Cast
{
	/**
	 * @var class-string
	 */
	public string $class;

	/**
	 * @param string $class Fully qualified class name to cast to
	 */
	public function __construct(string $class)
	{
		$this->class = $class;
	}
}
