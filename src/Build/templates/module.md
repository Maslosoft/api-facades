```php
<?php
declare(strict_types=1);

namespace {{ns}};

{{uses}}

/**
 * AUTO-GENERATED module facade. Do not edit.
 * Tag: {{tag}}
 */
final class {{class}} extends {{extends}}
{
    public function __construct(private {{genClientShort}} $client) {}

{{moduleMethods}}

{{operationMethods}}

    public function __get(string $name)
    {
        $map = [
{{magicMap}}
        ];
        return isset($map[$name]) ? $map[$name]() : throw new \OutOfBoundsException('Unknown API group: ' . $name);
    }
}
```
