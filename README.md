# API Facades
API Facades generator, allowing to call OpenAPI compatible API's with fluid interfaces.

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

## Unit tests

To create unit test, `make` may be used with self-explanatory command, for example:

```bash
make unit Generate/Trim
```

Will generate new unit test class in:

```
tests/Unit/GenerateTrimTest.php
```

Keep in mind to use forward slash for namespace of tests, as `\` may be interpreted as ascape character and generated class namespece will be wrong.

## Templates

Templates are stored as md files. This allows syntax highlighting, while not showing errors when using placeholders.
