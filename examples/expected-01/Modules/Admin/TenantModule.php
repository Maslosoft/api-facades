<?php

namespace Examples\Expected01\Modules\Admin;

use Examples\Expected01\Verbs\Admin\Tenant\BlockVerb;
use Examples\Expected01\Verbs\Admin\Tenant\UnblockVerb;
use Maslosoft\ApiFacades\Base\GenericModule;
use Maslosoft\ApiFacades\Models\Verb;

class TenantModule extends GenericModule
{
	public BlockVerb $block;
	public UnblockVerb $unblock;
	public Verb $activate;
	public Verb $deactivate;

	public function __construct($client)
	{
		$this->block = new BlockVerb($client);
		$this->unblock = new UnblockVerb($client);
		$this->activate = new Verb("post", $client, "admin/tenants/activate");
		$this->deactivate = new Verb("post", $client, "admin/tenants/deactivate");
	}
}
