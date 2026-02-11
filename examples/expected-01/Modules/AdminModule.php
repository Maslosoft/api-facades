<?php

namespace Examples\Expected01\Modules;

use Examples\Expected01\Modules\Admin\TenantModule;
use Maslosoft\ApiFacades\Models\Verb;

class AdminModule
{
	public Verb $runs;

	public Verb $run;

	public TenantModule $tenant;

	public function __construct($client)
	{
		$this->client = $client;
		$this->runs = new Verb("get");
		$this->run = new Verb(["get", "delete"]);
		$this->tenant = new TenantModule();
	}

	public function runs()
	{
		return $this->runs->get();
	}
}
