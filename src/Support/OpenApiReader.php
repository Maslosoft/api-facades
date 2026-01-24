<?php

namespace Maslosoft\ApiFacades\Support;

class OpenApiReader
{
	public function read(string $path)
	{
		// TODO:
		// 1. Read openapi.json specs file
		// 2. Build abstract structure using:
		// 2.a. Maslosoft\ApiFacades\Models\Resource
		// 2.b. Maslosoft\ApiFacades\Models\Op
		// 2.c. Maslosoft\ApiFacades\Models\Model <-- not exists yet, should be created based on schemas
		// Return class instance containing OOP composed OpenApi specs
	}
}