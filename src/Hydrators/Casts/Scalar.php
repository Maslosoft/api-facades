<?php

namespace Maslosoft\ApiFacades\Hydrators\Casts;

use Attribute;

/**
 * Attribute for casting single value into PHP scalar. When using type definition it's not required or can by union type.
 *
 * Usage:
 * ```php
 *     // Cast to string explicitly
 *     #[Scalar(Scalar::string)]
 *     public int|string $item;
 *
 * 	   // Cast to int automatically, based on type
 *     public int $autoType;
 * ```
 *
 *  Not needed if type is defined, and it's only one type (union types are not supported without annotation):
 *  ```php
 *      public ?int $item = null;
 *  ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Scalar
{
	public const string string = 'string';
	public const string int = 'int';
	public const string bool = 'bool';
	public const string float = 'float';
}
