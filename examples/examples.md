# This folder contains expected or test examples

## Expected 01

This example shows how the generated classes should work.

The most simple example is to get `admin/runs`, so we first need to instantiate the client,
and then just call relevant Verb methods, names path is same as URL:

```php
use Examples\Expected01\Client01;

$client = new Client01();
$client->admin->runs->get();
```

The runs result is populated by chosen hydrator and model type.

Each Verb is custom generated, so it hints exact parameters and hydrated return types.
Also, because only relevant verbs and modules exist, the non-existing endpoints will not
be type hinted.

OpenAPI spec used: `./openapi.01.json`
API Facades config used: `./api-facades.01.yml`

If any the models have some kind of namespace, for example dot separated names,
like `crm.User`, the namespace should be also used in model generator, and
resuling in combined base namespace from yml config and partial model namespace:

Example:

OpenApi namespace:

`crm.User`

The namespace in yml config:

`Examples\Expected01`

Resulting namespace:

`Examples\Expected01\Models\Crm\User`
