<?php

namespace Maslosoft\ApiFacades\Hydrators;

use Maslosoft\ApiFacades\Exceptions\UnsupportedTypeException;
use Maslosoft\ApiFacades\Hydrators\Casts\Cast;
use Maslosoft\ApiFacades\Hydrators\Casts\CastArray;

class ObjectProperties
{
	/**
	 * Populates the properties of the given object with the corresponding values from the provided data array. The
	 * object's properties must be public and writable and defined explicitly in code, as hydrator uses reflection to enumerate and analyze them.
	 *
	 * The hydrator uses property types to infer the correct data type for each property. Only single typed properties are supported,
	 * union types are not supported because that would cause ambiguity. The union types are allowed only if used together with explicit
	 * #[Cast] or #[Scalar] attribute.
	 *
	 * The hydrator can use `Cast` attributes to cast values into specific types, or `CastArray` to cast arrays of objects.
	 *
	 * The hydrator works recursively, so nested objects are also hydrated whether the property should be casted to object.
	 *
	 * @see Cast
	 * @see CastArray
	 * @throws UnsupportedTypeException If property type is union type.
	 * @param object $object The object to be hydrated.
	 * @param array $data An associative array containing the data to apply to the object.
	 * @return object The hydrated object with updated properties.
	 */
	public function hydrate(object $object, array $data): object
	{
		// TODO: Implement hydrate() method as in method description.
	}
}
