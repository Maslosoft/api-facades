<?php

namespace Examples\Expected01\Modules;

use Examples\Expected01\Client01;
use Examples\Expected01\Models\Tenant;
use Examples\Expected01\Modules\Admin\TenantModule;
use Examples\Expected01\Verbs\Admin\RunsVerb;
use Maslosoft\ApiFacades\Base\GenericModule;
use Maslosoft\ApiFacades\Models\Verb;

class AdminModule extends GenericModule
{
	private Client01 $client;

	public RunsVerb $runs;

	public Verb $run;

	public TenantModule $tenant;

	public function __construct($client)
	{
		$this->client = $client;
		$this->runs = new RunsVerb($client);
		$this->run = new Verb(["get", "delete"], $client, "admin/runs");
		$this->tenant = new TenantModule($client);
	}
}
