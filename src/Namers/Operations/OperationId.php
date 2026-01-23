<?php

namespace Maslosoft\ApiFacades\Namers\Operations;

use Maslosoft\ApiFacades\Interfaces\OperationNamer;

class OperationId implements OperationNamer
{
	private string $operationId;

	public function __construct(string $operationId)
	{
		$this->operationId = $operationId;
	}

	public function getName(): string
	{
		$operationId = trim($this->operationId);
		if($operationId === '')
		{
			return '';
		}
		$parts = preg_split('/[.\/]+/', $operationId);
		$segment = is_array($parts) ? (string)end($parts) : $operationId;

		if(preg_match('/[^a-zA-Z0-9]/', $segment))
		{
			$segment = preg_replace('/[^a-zA-Z0-9]+/', ' ', $segment);
			$segment = str_replace(' ', '', ucwords(strtolower(trim($segment))));
			return $segment === '' ? '' : lcfirst($segment);
		}
		return $segment === '' ? '' : lcfirst($segment);
	}
}
