# This folder contains expected or test examples

## Expected 01

This example shows how the generated classes should work.

The most simple example is to get `admin/runs`, so we first need to instantiate the client,
and then just call relevent methods, names same as URL:

```php
use Examples\Expected01\Client01;

$client = new Client01();
$client->admin->runs();
```

The runs result is populated by chosen hydrator and model type.
