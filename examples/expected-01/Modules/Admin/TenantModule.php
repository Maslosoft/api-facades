<?php

namespace Examples\Expected01\Modules\Admin;

use Maslosoft\ApiFacades\Base\GenericModule;
use Maslosoft\ApiFacades\Models\Verb;

class TenantModule extends GenericModule
{
	public Verb $block;
	public Verb $unblock;
	public Verb $activate;
	public Verb $deactivate;

	public function __construct($client)
	{
		$this->block = new Verb("post", $client, "admin/tenants/block");
		$this->unblock = new Verb("post", $client, "admin/tenants/unblock");
		$this->activate = new Verb("post", $client, "admin/tenants/activate");
		$this->deactivate = new Verb("post", $client, "admin/tenants/deactivate");
	}
}
