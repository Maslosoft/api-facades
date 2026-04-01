<?php

namespace Examples\Expected01\Verbs\Admin;

use Examples\Expected01\Models\Tenant;
use Maslosoft\ApiFacades\Hydrators\Items;
use Maslosoft\ApiFacades\Models\Base\CustomVerb;

class RunsVerb extends CustomVerb
{
	/**
	 * @return Tenant[]
	 */
	public function get(): array
	{
		return Items::hydrate($this->client->getHydrator(), Tenant::class, $this->client->getData('/api/admin/runs', 'get'));
	}
}
