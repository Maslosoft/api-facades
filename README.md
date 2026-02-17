# API Facades

API Facades generator, allowing to call OpenAPI compatible API's with fluid interfaces.

### 🤖✨👱🏻 Made by human an Ai

Project developed with help of an Ai. This project is **still in development**, and
only features currently required are being implemented and tested. 

## Description

The idea is to use natural path-like syntax, instead of method names clumps. The API call is basically last
method which is the verb of request.

For example, `POST` to user account module API:

```php
$api->ua->user->login->post($request);
```

Which sends post to: htttps://example.com/api/v1/ua/user/login

Example with `GET`:

```php
$api->settlements->balance->show->get($request);
```

Or even shorter syntax:

```php
$api->settlements->balance->show($request);
```

Which gets response from: htttps://example.com/api/v1/settlements/balance/show

### Install

```bash
composer require maslosoft/api-facades --dev
```

## Hydration

The `ObjectProperties` hydrator populates public properties using reflection. It supports `#[Cast]`, `#[CastArray]`,
`#[Scalar]`, and `#[ScalarArray]` attributes.

### Input field mapping and camelization

Use `#[InputField('field_name')]` to map an input field to a property. When an input field is explicitly defined,
camelization is ignored for that property.

Camelizing input keys (for example, `user_name` -> `userName`) can be configured through `HydrationConfig`:
- `HydrationConfig::CamelizeAuto` (default) - camelize when snake_case keys are present.
- `HydrationConfig::CamelizeEnabled` - always allow snake_case mapping.
- `HydrationConfig::CamelizeDisabled` - disable snake_case mapping.

```php
use Maslosoft\ApiFacades\Hydrators\Attributes\InputField;
use Maslosoft\ApiFacades\Hydrators\HydrationConfig;
use Maslosoft\ApiFacades\Hydrators\ObjectProperties;

class User
{
	#[InputField('user_name')]
	public string $userName = '';

	public string $emailAddress = '';
}

$hydrator = new ObjectProperties(new HydrationConfig(HydrationConfig::CamelizeAuto));
$user = $hydrator->hydrate(new User(), [
	'user_name' => 'Jane',
	'email_address' => 'jane@example.com',
]);
```

## Unit tests

To create unit test, `make` may be used with self-explanatory command, for example:

```bash
make unit Generate/Trim
```

Will generate new unit test class in:

```
tests/Unit/GenerateTrimTest.php
```

Keep in mind to use forward slash for namespace of tests, as `\` may be interpreted as escape character and generated class namespece will be wrong.

## Templates

Templates are stored as md files. This allows syntax highlighting while not showing errors when using placeholders.
