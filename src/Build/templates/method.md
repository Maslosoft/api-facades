```php
    /**
     * {{httpList}} {{path}}
     * @return Verb<{{TGet}} , {{TPost}} , {{TPut}} , {{TDelete}} , {{TPatch}}>
     */
    public function {{name}}(): Verb
    {
        return new Verb([
{{verbMap}}
        ], self::class, '{{name}}');
    }
```
