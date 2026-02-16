<?php

namespace Maslosoft\ApiFacades\Hydrators;

use Maslosoft\ApiFacades\Hydrators\Attributes\InputField;
use Maslosoft\ApiFacades\Exceptions\UnsupportedTypeException;
use Maslosoft\ApiFacades\Hydrators\Casts\Cast;
use Maslosoft\ApiFacades\Hydrators\Casts\CastArray;
use Maslosoft\ApiFacades\Hydrators\Casts\Scalar;
use Maslosoft\ApiFacades\Hydrators\Casts\ScalarArray;
use Maslosoft\ApiFacades\Interfaces\Hydrator;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionProperty;
use ReflectionUnionType;

class ObjectProperties implements Hydrator
{
	private HydrationConfig $config;

	public function __construct(?HydrationConfig $config = null)
	{
		$this->config = $config ?? new HydrationConfig();
	}

	/**
	 * Populates the properties of the given object with the corresponding values from the provided data array. The
	 * object's properties must be public and writable and defined explicitly in code, as hydrator uses reflection to enumerate and analyze them.
	 *
	 * The hydrator uses property types to infer the correct data type for each property. Only single typed properties are supported,
	 * union types are not supported because that would cause ambiguity. The union types are allowed only if used together with explicit
	 * #[Cast] or #[Scalar] attribute.
	 *
	 * The hydrator can use #[Cast] attributes to cast values into specific types (classes), or #[CastArray] to cast arrays of objects.
	 * It can also use #[InputField('field_name')] to map input field names to properties.
	 *
	 * The hydrator works recursively, so nested objects are also hydrated whether the property should be casted to object.
	 *
	 * @see Cast
	 * @see CastArray
	 * @throws UnsupportedTypeException If property type is union type.
	 * @param object $object The object (data model) to be hydrated.
	 * @param array $data An associative array containing the data to apply to the object.
	 * @return object The hydrated object with updated properties.
	 */
	public function hydrate(object $object, array $data): object
	{
		$reflection = new ReflectionObject($object);
		$camelizeInput = $this->config->shouldCamelizeInput($data);

		foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property)
		{
			if ($property->isStatic())
			{
				continue;
			}

			$inputFieldName = $this->resolveInputFieldName($property, $data, $camelizeInput);
			if ($inputFieldName === null)
			{
				continue;
			}

			$propertyName = $property->getName();
			$value = $data[$inputFieldName];

			$castAttributes = $property->getAttributes(Cast::class);
			if (!empty($castAttributes))
			{
				$cast = $castAttributes[0]->newInstance();
				$property->setValue($object, $this->hydrateObjectValue($cast->class, $value));
				continue;
			}

			$castArrayAttributes = $property->getAttributes(CastArray::class);
			if (!empty($castArrayAttributes))
			{
				$castArray = $castArrayAttributes[0]->newInstance();
				$property->setValue($object, $this->hydrateObjectArray($castArray->class, $value));
				continue;
			}

			$scalarAttributes = $property->getAttributes(Scalar::class);
			if (!empty($scalarAttributes))
			{
				$scalar = $scalarAttributes[0]->newInstance();
				$property->setValue($object, $this->castScalarValue($value, $scalar->type));
				continue;
			}

			$scalarArrayAttributes = $property->getAttributes(ScalarArray::class);
			if (!empty($scalarArrayAttributes))
			{
				$scalarArray = $scalarArrayAttributes[0]->newInstance();
				$property->setValue($object, $this->castScalarArrayValue($value, $scalarArray->type));
				continue;
			}

			$type = $property->getType();
			if ($type instanceof ReflectionUnionType)
			{
				throw new UnsupportedTypeException(sprintf(
					'Property `%s::%s` uses union type without explicit cast.',
					$reflection->getName(),
					$propertyName
				));
			}

			if (!$type instanceof ReflectionNamedType)
			{
				$property->setValue($object, $value);
				continue;
			}

			if ($value === null)
			{
				$property->setValue($object, null);
				continue;
			}

			$typeName = $type->getName();
			if ($type->isBuiltin())
			{
				$property->setValue($object, $this->castBuiltinValue($value, $typeName));
				continue;
			}

			$property->setValue($object, $this->hydrateObjectValue($typeName, $value));
		}

		return $object;
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function resolveInputFieldName(ReflectionProperty $property, array $data, bool $camelizeInput): ?string
	{
		$inputFieldAttributes = $property->getAttributes(InputField::class);
		if (!empty($inputFieldAttributes))
		{
			$inputField = $inputFieldAttributes[0]->newInstance();
			return array_key_exists($inputField->name, $data) ? $inputField->name : null;
		}

		$propertyName = $property->getName();
		if (array_key_exists($propertyName, $data))
		{
			return $propertyName;
		}

		if (!$camelizeInput)
		{
			return null;
		}

		$snakeName = $this->toSnakeCase($propertyName);
		if ($snakeName !== $propertyName && array_key_exists($snakeName, $data))
		{
			return $snakeName;
		}

		return null;
	}

	private function toSnakeCase(string $value): string
	{
		$snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $value);
		return strtolower($snake ?? $value);
	}

	private function castBuiltinValue(mixed $value, string $type): mixed
	{
		return match ($type)
		{
			'int' => (int)$value,
			'float' => (float)$value,
			'bool' => (bool)$value,
			'string' => (string)$value,
			'array' => (array)$value,
			'object' => (object)$value,
			default => $value,
		};
	}

	private function castScalarValue(mixed $value, string $type): mixed
	{
		if ($value === null)
		{
			return null;
		}

		return match ($type)
		{
			Scalar::int => (int)$value,
			Scalar::float => (float)$value,
			Scalar::bool => (bool)$value,
			Scalar::string => (string)$value,
			default => $value,
		};
	}

	private function castScalarArrayValue(mixed $value, string $type): mixed
	{
		if ($value === null || !is_array($value))
		{
			return $value;
		}

		$casted = [];
		foreach ($value as $key => $item)
		{
			$casted[$key] = $this->castScalarValue($item, $type);
		}

		return $casted;
	}

	private function hydrateObjectValue(string $class, mixed $value): mixed
	{
		if ($value === null)
		{
			return null;
		}

		if (is_object($value))
		{
			return $value;
		}

		if (!is_array($value))
		{
			return $value;
		}

		$instance = new $class();
		$this->hydrate($instance, $value);

		return $instance;
	}

	private function hydrateObjectArray(string $class, mixed $value): mixed
	{
		if ($value === null || !is_array($value))
		{
			return $value;
		}

		$items = [];
		foreach ($value as $key => $item)
		{
			if (is_object($item))
			{
				$items[$key] = $item;
				continue;
			}

			if (!is_array($item))
			{
				$items[$key] = $item;
				continue;
			}

			$instance = new $class();
			$this->hydrate($instance, $item);
			$items[$key] = $instance;
		}

		return $items;
	}
}
