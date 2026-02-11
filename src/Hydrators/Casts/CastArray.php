<?php

namespace Maslosoft\ApiFacades\Hydrators\Casts;

use Attribute;

/**
 * Attribute for casting array of arrays into array of objects.
 *
 * This attribute is supposed to be used with `JsonConvertibleInterfaceInterface`
 *
 * Usage:
 * ```php
 *     #[CastArray(RevenueItem::class)]
 *     public array $items = [];
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class CastArray
{
	/**
	 * @var class-string
	 */
	public string $class;

	/**
	 * @param class-string $class Fully qualified class name of item type
	 */
	public function __construct(string $class)
	{
		$this->class = $class;
	}
}
