<?php

namespace Examples\Expected01;

use Examples\Expected01\Modules\AdminModule;
use Maslosoft\ApiFacades\Base\GenericClient;
use Maslosoft\ApiFacades\Hydrators\ObjectProperties;

class Client01 extends GenericClient
{
	public AdminModule $admin;

	public function __construct()
	{
		// Hydrator from configuration
		$this->setHydrator(new ObjectProperties);
		// Modules from openapi specs
		$this->admin = new AdminModule($this);
	}
}
