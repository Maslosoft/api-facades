```php
<?php
declare(strict_types=1);

namespace {{ns}};

{{uses}}

/**
 * AUTO-GENERATED operation facade. Do not edit.
 * Name: {{tag}}
 * Path: {{path}}
 */
final class {{class}} extends {{extends}}
{
    public function __construct(private {{genClientShort}} $client) {}

{{verbMethods}}
}
```
