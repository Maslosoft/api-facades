<?php

use Examples\Expected01\Models\AckResponse;
use Maslosoft\ApiFacades\Models\Base\BaseVerb;

class BlockVerb extends BaseVerb
{
	public function post($id): AckResponse
	{
		return $this->client->getHydrator()->hydrate(new AckResponse, $this->client->getData('/api/admin/tenant/block/{id}', 'post', ['id' => $id]));
	}

}
