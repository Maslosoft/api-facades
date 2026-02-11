<?php

namespace Examples\Expected01;

use Examples\Expected01\Modules\AdminModule;

class Client01
{
	public AdminModule $admin;

	public function __construct()
	{
		$this->admin = new AdminModule($this);
	}
}
