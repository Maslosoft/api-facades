<?php

namespace Maslosoft\ApiFacades\Hydrators\Casts;

use Attribute;

/**
 * Attribute for casting single value into PHP scalar. When using type definition it's not required or can by union type.
 *
 * Usage:
 * ```php
 *     #[Scalar(Scalar::string)]
 *     public RevenueItem $item;
 * ```
 *
 *  Not needed if type is defined, and it's only one type (union types are not supported):
 *  ```php
 *      public ?RevenueItem $item = null;
 *  ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Scalar
{
	public const string string = 'string';
	public const string int = 'int';
	// TODO: Define all PHP scalar types
}
