<?php

namespace Tests\Unit\Hydrators;

use Maslosoft\ApiFacades\Exceptions\UnsupportedTypeException;
use Maslosoft\ApiFacades\Hydrators\HydrationConfig;
use Maslosoft\ApiFacades\Hydrators\ObjectProperties;
use Tests\Models\Hydration\ArrayCastModel;
use Tests\Models\Hydration\CastChild;
use Tests\Models\Hydration\CamelizeModel;
use Tests\Models\Hydration\DefaultValuesModel;
use Tests\Models\Hydration\InputFieldModel;
use Tests\Models\Hydration\InterfaceParentModel;
use Tests\Models\Hydration\ParentModel;
use Tests\Models\Hydration\ScalarArrayModel;
use Tests\Models\Hydration\ScalarModel;
use Tests\Models\Hydration\UnionScalarModel;
use Tests\Models\Hydration\UnionUnsupportedModel;
use Tests\Models\Hydration\UntypedModel;
use Tests\Support\Unit;

class ObjectPropertiesTest extends Unit
{
	protected function _before(): void
	{
		$this->includeAll(dirname(__DIR__, 2) . '/Models');
	}

	public function testHydratesScalarTypesFromPropertyTypes(): void
	{
		$hydrator = new ObjectProperties();
		$model = new ScalarModel();

		$hydrator->hydrate($model, [
			'intValue' => '42',
			'floatValue' => '3.5',
			'boolValue' => 1,
			'stringValue' => 123,
		]);

		$this->assertSame(42, $model->intValue);
		$this->assertSame(3.5, $model->floatValue);
		$this->assertTrue($model->boolValue);
		$this->assertSame('123', $model->stringValue);
	}

	public function testHydratesSnakeCaseInputInAutoMode(): void
	{
		$hydrator = new ObjectProperties();
		$model = new CamelizeModel();

		$hydrator->hydrate($model, [
			'user_name' => 'Jane',
			'email_address' => 'jane@example.com',
		]);

		$this->assertSame('Jane', $model->userName);
		$this->assertSame('jane@example.com', $model->emailAddress);
	}

	public function testHydratesSnakeCaseInputInEnabledMode(): void
	{
		$hydrator = new ObjectProperties(new HydrationConfig(HydrationConfig::CamelizeEnabled));
		$model = new CamelizeModel();

		$hydrator->hydrate($model, [
			'user_name' => 'Kate',
		]);

		$this->assertSame('Kate', $model->userName);
	}

	public function testDisablesCamelizeInput(): void
	{
		$hydrator = new ObjectProperties(new HydrationConfig(HydrationConfig::CamelizeDisabled));
		$model = new CamelizeModel();

		$hydrator->hydrate($model, [
			'user_name' => 'Ignored',
		]);

		$this->assertSame('', $model->userName);
	}

	public function testHydratesUsingInputFieldAttribute(): void
	{
		$hydrator = new ObjectProperties();
		$model = new InputFieldModel();

		$hydrator->hydrate($model, [
			'user_name' => 'John',
			'status' => 'ok',
		]);

		$this->assertSame('John', $model->userName);
		$this->assertSame('ok', $model->status);
	}

	public function testInputFieldAttributeIgnoresCamelization(): void
	{
		$hydrator = new ObjectProperties(new HydrationConfig(HydrationConfig::CamelizeEnabled));
		$model = new InputFieldModel();

		$hydrator->hydrate($model, [
			'userName' => 'Wrong',
		]);

		$this->assertSame('default', $model->userName);
	}

	public function testHydratesScalarFromUnionUsingAttribute(): void
	{
		$hydrator = new ObjectProperties();
		$model = new UnionScalarModel();

		$hydrator->hydrate($model, [
			'value' => 123,
		]);

		$this->assertSame('123', $model->value);
	}

	public function testHydratesScalarArrayFromAttribute(): void
	{
		$hydrator = new ObjectProperties();
		$model = new ScalarArrayModel();

		$hydrator->hydrate($model, [
			'values' => ['1', 2, 3.9],
		]);

		$this->assertSame([1, 2, 3], $model->values);
	}

	public function testHydratesNestedObjectsByType(): void
	{
		$hydrator = new ObjectProperties();
		$model = new ParentModel();

		$hydrator->hydrate($model, [
			'title' => 'root',
			'nested' => [
				'name' => 'child',
				'leaf' => [
					'count' => '7',
				],
			],
		]);

		$this->assertSame('root', $model->title);
		$this->assertSame('child', $model->nested->name);
		$this->assertSame(7, $model->nested->leaf->count);
	}

	public function testHydratesUsingCastAttributeForInterface(): void
	{
		$hydrator = new ObjectProperties();
		$model = new InterfaceParentModel();

		$hydrator->hydrate($model, [
			'child' => [
				'value' => 'casted',
			],
		]);

		$this->assertInstanceOf(CastChild::class, $model->child);
		$this->assertSame('casted', $model->child->value);
	}

	public function testHydratesUsingCastArrayAttribute(): void
	{
		$hydrator = new ObjectProperties();
		$model = new ArrayCastModel();

		$hydrator->hydrate($model, [
			'items' => [
				[
					'name' => 'first',
					'leaf' => ['count' => 1],
				],
				[
					'name' => 'second',
					'leaf' => ['count' => 2],
				],
			],
		]);

		$this->assertCount(2, $model->items);
		$this->assertSame('first', $model->items[0]->name);
		$this->assertSame(1, $model->items[0]->leaf->count);
		$this->assertSame('second', $model->items[1]->name);
		$this->assertSame(2, $model->items[1]->leaf->count);
	}

	public function testUnionTypeWithoutCastThrows(): void
	{
		$hydrator = new ObjectProperties();
		$model = new UnionUnsupportedModel();

		$this->expectException(UnsupportedTypeException::class);

		$hydrator->hydrate($model, [
			'value' => 'nope',
		]);
	}

	public function testMissingDataDoesNotOverrideDefaults(): void
	{
		$hydrator = new ObjectProperties();
		$model = new DefaultValuesModel();

		$hydrator->hydrate($model, [
			'name' => 'updated',
		]);

		$this->assertSame('updated', $model->name);
		$this->assertSame(5, $model->count);
	}

	public function testUntypedPropertyGetsRawValue(): void
	{
		$hydrator = new ObjectProperties();
		$model = new UntypedModel();

		$payload = [
			'raw' => ['nested' => 'value'],
		];

		$hydrator->hydrate($model, $payload);

		$this->assertSame(['nested' => 'value'], $model->raw);
	}
}
