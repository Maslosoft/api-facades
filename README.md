# API Facades
API Facades generator, allowing to call OpenAPI compatible API's with fluid interfaces

### Install

```bash
composer require maslosoft/api-facades --dev
```

## Unit tests

To create unit test, make may be used with self-explanatory command, for example:

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
