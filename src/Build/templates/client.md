```php
<?php
declare(strict_types=1);

namespace {{nsRoot}};

use {{genClientFqcn}};
use {{nsFacades}} as Facades;

/**
 * AUTO-GENERATED root facade. Do not edit.
 */
final class TraderClient
{
    public function __construct(private {{genClientFqcn}} $client) {}

    /** Build using Generated\Client::create() if available */
    public static function create(): self
    {
        $gen = '{{genClientFqcn}}';
        if (is_callable([$gen, 'create'])) {
            /** @var {{genClientFqcn}} $c */
            $c = $gen::create();
            return new self($c);
        }
        throw new \RuntimeException('Generated\\Client::create() not found.');
    }

    /** Access underlying generated client */
    public function generated(): {{genClientFqcn}}
    {
        return $this->client;
    }

{{tagMethods}}

    public function __get(string $name)
    {
        $map = [
{{magicMap}}
        ];
        return $map[$name] ? $map[$name]() : throw new \OutOfBoundsException('Unknown API group: ' . $name);
    }
}
```
