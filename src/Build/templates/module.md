```php
<?php
declare(strict_types=1);

namespace {{ns}};

use {{genClientFqcn}};
use {{verbFqcn}};

/**
 * AUTO-GENERATED. Do not edit.
 * Tag: {{tag}}
 */
final class {{class}}
{
    public function __construct(private {{genClientFqcn}} $client) {}

{{methods}}
}
```
