<?php

namespace Maslosoft\ApiFacades\Hydrators\Casts;

use Attribute;

/**
 * Attribute for casting multiple, ie array values into PHP explicit array of scalar types. This annotation allows to have guaranteed types in array.
 *
 * Usage:
 * ```php
 *     // Cast to string explicitly
 *     #[ScalarArray(Scalar::string)]
 *     public int|string $item;
 * ```
 * @see ScalarArray
 * @see Scalar
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ScalarArray
{
	public const string string = 'string';
	public const string int = 'int';
	public const string bool = 'bool';
	public const string float = 'float';

	public string $type;

	/**
	 * @param string $type One of ScalarArray::string, ScalarArray::int, ScalarArray::bool, ScalarArray::float
	 */
	public function __construct(string $type)
	{
		$this->type = $type;
	}
}
