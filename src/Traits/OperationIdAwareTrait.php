<?php

namespace Maslosoft\ApiFacades\Traits;

trait OperationIdAwareTrait
{
	public string $operationId;

	public function getOperationId(): string
	{
		return $this->operationId;
	}

	public function setOperationId(string $operationId): static
	{
		$this->operationId = $operationId;
		return $this;
	}
}