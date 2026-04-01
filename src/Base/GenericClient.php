<?php

namespace Maslosoft\ApiFacades\Base;

use Maslosoft\ApiFacades\Exceptions\BadParamsException;
use Maslosoft\ApiFacades\Interfaces\Hydrator;
use BackedEnum;
use JsonException;
use RuntimeException;

abstract class GenericClient
{
	/**
	 * TODO: Mark as final on newer PHP versions
	 * @final
	 * @var Hydrator
	 */
	private /*final*/ Hydrator $_hydrator;

	/**
	 * Example base URL: https://api.example.com
	 * @var string
	 */
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
	 * @return mixed
	 */
	public function getData(string $endpoint, string $method, array $params = [], mixed $body = []): mixed
	{
		$method = strtoupper($method);
		$endpoint = $this->applyParamsToEndpoint($endpoint, $params);

		$baseUrl = rtrim($this->baseUrl, '/');
		$endpoint = '/' . ltrim($endpoint, '/');
		$url = $baseUrl . $endpoint;

		if ($params !== [])
		{
			$query = http_build_query($params);
			if ($query !== '')
			{
				$url .= (str_contains($url, '?') ? '&' : '?') . $query;
			}
		}

		$headers = ['Accept: application/json'];
		$payload = null;
		if ($body !== [] && $body !== null)
		{
			try
			{
				$payload = json_encode($this->normalizeBody($body), JSON_THROW_ON_ERROR);
			}
			catch (JsonException $exception)
			{
				throw new BadParamsException('Request body could not be encoded as JSON.', 0, $exception);
			}
			$headers[] = 'Content-Type: application/json';
		}

		$response = $this->request($url, $method, $headers, $payload);
		if (trim($response) === '')
		{
			return [];
		}

		try
		{
			return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
		}
		catch (JsonException)
		{
			return $response;
		}
	}

	private function applyParamsToEndpoint(string $endpoint, array &$params): string
	{
		if (preg_match_all('/{([^}]+)}/', $endpoint, $matches))
		{
			$used = [];
			foreach ($matches[1] as $name)
			{
				if (isset($used[$name]))
				{
					continue;
				}
				if (!array_key_exists($name, $params))
				{
					throw new BadParamsException("Missing required parameter `{$name}`.");
				}
				$value = $params[$name];
				if (!is_scalar($value) && $value !== null)
				{
					throw new BadParamsException("Parameter `{$name}` must be a scalar value.");
				}
				$endpoint = str_replace('{' . $name . '}', rawurlencode((string)$value), $endpoint);
				unset($params[$name]);
				$used[$name] = true;
			}
		}

		return $endpoint;
	}

	protected function request(string $url, string $method, array $headers, ?string $body): string
	{
		$options = [
			'http' => [
				'method' => strtoupper($method),
				'header' => implode("\r\n", $headers),
				'ignore_errors' => true,
			],
		];

		if ($body !== null)
		{
			$options['http']['content'] = $body;
		}

		$context = stream_context_create($options);
		$response = file_get_contents($url, false, $context);
		if ($response === false)
		{
			throw new RuntimeException("Unable to fetch '{$url}'.");
		}

		return $response;
	}

	private function normalizeBody(mixed $value): mixed
	{
		if ($value instanceof BackedEnum)
		{
			return $value->value;
		}

		if (is_array($value))
		{
			foreach ($value as $key => $item)
			{
				$value[$key] = $this->normalizeBody($item);
			}

			return $value;
		}

		if (is_object($value))
		{
			$result = [];
			foreach (get_object_vars($value) as $key => $item)
			{
				$result[$key] = $this->normalizeBody($item);
			}

			return $result;
		}

		return $value;
	}
}
