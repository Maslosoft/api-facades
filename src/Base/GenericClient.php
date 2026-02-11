<?php

namespace Maslosoft\ApiFacades\Base;

use Maslosoft\ApiFacades\Exceptions\BadParamsException;
use Maslosoft\ApiFacades\Interfaces\Hydrator;

abstract class GenericClient
{
	/**
	 * TODO: Mark as final on newer PHP versions
	 * @final
	 * @var Hydrator
	 */
	private /*final*/ Hydrator $_hydrator;

	public /*final*/ string $baseUrl;

	public function getHydrator(): Hydrator
	{
		return $this->_hydrator;
	}

	public function setHydrator(Hydrator $_hydrator): static
	{
		$this->_hydrator = $_hydrator;
		return $this;
	}

	/**
	 * Get data from API as PHP array. The endpoint is relative to the base URL, and it should be provided
	 * in the same form as in OpenApi specification. If specification includes parameters, these should
	 * be also provided as an associative array with the keys matching the parameter names in endpoint URL.
	 *
	 * Example:
	 * ```
	 * $client->getData('/api/users/{id}', 'get', ['id' => 1]);
	 * ```
	 *
	 * The optional `$body` parameter can be used to provide request body as PHP, JSON convertible array.
	 *
	 * @param string $endpoint Endpoint URL, relative to the base URL, as defined in openapi specification, including parameters in curly braces.
	 * @param string $method Method as per HTTP specification, e.g. 'get', 'post', 'put', 'delete'
	 * @param array $params Array key-values matching parameter names in endpoint URL.
	 * @param mixed $body Arbitrary data to be sent as request body, the only requirement is that it can be converted to JSON.
	 * @return array
	 */
	public function getData(string $endpoint, string $method, array $params = [], mixed $body = []): array
	{
		// TODO: Implement getData() method.
		return [];
	}
}
