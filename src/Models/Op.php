<?php

namespace Maslosoft\ApiFacades\Models;

final class Op {
	public string $tag;
	public string $path;
	public string $http;            // GET/POST/...
	public string $operationId;
	public string $janeMethod;      // camelCased opId
	public string $returnDoc;       // phpdoc type for this verb (e.g. \NS\Model\Foo|array<int,\NS\Model\Bar>|mixed)
}
