<?php

namespace Examples\Expected01\Verbs\Admin\Tenant;

use Examples\Expected01\Models\AckResponse;
use Maslosoft\ApiFacades\Models\Base\CustomVerb;

class UnblockVerb extends CustomVerb
{
	public function post($id): AckResponse
	{
		return $this->client->getHydrator()->hydrate(new AckResponse, $this->client->getData('/api/admin/tenant/unblock/{id}', 'post', ['id' => $id]));
	}

}
