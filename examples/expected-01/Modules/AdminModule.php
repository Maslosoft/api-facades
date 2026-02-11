<?php

namespace Examples\Expected01\Modules;

use Examples\Expected01\Client01;
use Examples\Expected01\Models\Tenant;
use Examples\Expected01\Modules\Admin\TenantModule;
use Maslosoft\ApiFacades\Base\GenericModule;
use Maslosoft\ApiFacades\Models\Verb;

class AdminModule extends GenericModule
{
	private Client01 $client;

	public Verb $runs;

	public Verb $run;

	public TenantModule $tenant;

	public function __construct($client)
	{
		$this->client = $client;
		$this->runs = new Verb("get", $client, "admin/runs");
		$this->run = new Verb(["get", "delete"], $client, "admin/runs");
		$this->tenant = new TenantModule($client);
	}

	/**
	 * @return Tenant[]
	 */
	public function runs(): array
	{
		return $this->runs->get();
	}

	public function run($id): Tenant
	{
		return $this->client->_hydrator->hydrate(new Tenant, $this->run->get($id));
	}
}
