<?php

namespace Examples\Expected01\Models;

class Tenant
{
	public int $id;
	public string $name;
	public string $email;
	public string $type;
	public bool $active;
	public bool $blocked;
}
