<?php

namespace Maslosoft\ApiFacades\Base;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Psr7\MultipartStream;
use Maslosoft\ApiFacades\Exceptions\BadParamsException;
use Maslosoft\ApiFacades\Exceptions\Http\ClientException;
use Maslosoft\ApiFacades\Exceptions\Http\ForbiddenException;
use Maslosoft\ApiFacades\Exceptions\Http\HttpException;
use Maslosoft\ApiFacades\Exceptions\Http\ServerException;
use Maslosoft\ApiFacades\Exceptions\Http\TransportException;
use Maslosoft\ApiFacades\Exceptions\Http\UnauthorizedException;
use Maslosoft\ApiFacades\Interfaces\Hydrator;
use Psr\Http\Message\ResponseInterface;
use BackedEnum;
use JsonException;

abstract class GenericClient
{
	/**
	 * TODO: Mark as final on newer PHP versions
	 * @final
	 * @var Hydrator
	 */
	private /*final*/ Hydrator $_hydrator;

	private ?ClientInterface $httpClient = null;

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

	public function setHttpClient(ClientInterface $httpClient): static
	{
		$this->httpClient = $httpClient;
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
	 * The optional `$body` parameter can be used to provide request payload data. Encoding and transport details
	 * are controlled by the generated `$request` metadata argument.
	 *
	 * @param string $endpoint Endpoint URL, relative to the base URL, as defined in openapi specification, including parameters in curly braces.
	 * @param string $method Method as per HTTP specification, e.g. 'get', 'post', 'put', 'delete'
	 * @param array $params Array key-values matching parameter names in endpoint URL.
	 * @param mixed $body Arbitrary data to be sent as request body.
	 * @param array<string, scalar|\BackedEnum|null> $headers Additional request headers.
	 * @param array<string, mixed> $request Request transport metadata generated from the OpenAPI request body definition.
	 * @return mixed
	 */
	public function getData(
		string $endpoint,
		string $method,
		array $params = [],
		mixed $body = [],
		array $headers = [],
		array $request = []
	): mixed
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

		$requestHeaders['Accept'] = 'application/json';
		$payload = null;
		if ($body !== [] && $body !== null)
		{
			[$payload, $bodyHeaders] = $this->prepareRequestBody($body, $request);
			foreach ($bodyHeaders as $name => $value)
			{
				$requestHeaders[$name] = $value;
			}
		}

		$headers = array_merge($this->getHeaders(), $headers);

		foreach ($this->normalizeHeaders($headers) as $name => $header)
		{
			$requestHeaders[$name] = $header;
		}

		$response = $this->request($url, $method, $requestHeaders, $payload);
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
		try
		{
			$options = [
				'headers' => $headers,
				'http_errors' => false,
			];

			if ($body !== null)
			{
				$options['body'] = $body;
			}

			$response = $this->getHttpClient()->request(strtoupper($method), $url, $options);
		}
		catch (GuzzleRequestException $exception)
		{
			$response = $exception->getResponse();
			if ($response !== null)
			{
				throw $this->createHttpException($response, $method, $url, $exception);
			}

			throw new TransportException(
				$this->formatTransportMessage($method, $url, $exception->getMessage()),
				strtoupper($method),
				$url,
				$exception
			);
		}
		catch (GuzzleException $exception)
		{
			throw new TransportException(
				$this->formatTransportMessage($method, $url, $exception->getMessage()),
				strtoupper($method),
				$url,
				$exception
			);
		}

		if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300)
		{
			throw $this->createHttpException($response, $method, $url);
		}

		return (string)$response->getBody();
	}

	/**
	 * @override Override this method to provide custom headers.
	 * @return array
	 */
	protected function getHeaders(): array
	{
		return [];
	}

	protected function getHttpClient(): ClientInterface
	{
		return $this->httpClient ??= new Client();
	}

	/**
	 * @param array<string, mixed> $request
	 * @return array{0: string, 1: array<string, string>}
	 */
	private function prepareRequestBody(mixed $body, array $request): array
	{
		$mode = strtolower((string)($request['mode'] ?? 'json'));
		$contentType = (string)($request['contentType'] ?? '');

		return match ($mode)
		{
			'multipart' => $this->prepareMultipartBody($body, $request),
			'form' => $this->prepareFormBody($body, $contentType === '' ? 'application/x-www-form-urlencoded' : $contentType),
			'raw' => $this->prepareRawBody($body, $contentType),
			default => $this->prepareJsonBody($body, $contentType === '' ? 'application/json' : $contentType),
		};
	}

	/**
	 * @return array{0: string, 1: array<string, string>}
	 */
	private function prepareJsonBody(mixed $body, string $contentType): array
	{
		try
		{
			$payload = json_encode($this->normalizeBody($body), JSON_THROW_ON_ERROR);
		}
		catch (JsonException $exception)
		{
			throw new BadParamsException('Request body could not be encoded as JSON.', 0, $exception);
		}

		if (!is_string($payload))
		{
			throw new BadParamsException('Request body could not be encoded as JSON.');
		}

		return [$payload, ['Content-Type' => $contentType]];
	}

	/**
	 * @param array<string, mixed> $request
	 * @return array{0: string, 1: array<string, string>}
	 */
	private function prepareMultipartBody(mixed $body, array $request): array
	{
		$fields = isset($request['fields']) && is_array($request['fields']) ? $request['fields'] : [];
		$normalized = $this->normalizeBody($body);
		$data = $this->normalizeMultipartBodyData($normalized, $fields);
		$parts = [];
		$knownFields = [];

		foreach ($fields as $field)
		{
			if (!is_array($field) || !isset($field['name']))
			{
				continue;
			}

			$name = (string)$field['name'];
			$knownFields[$name] = true;
			if (!array_key_exists($name, $data))
			{
				if (($field['required'] ?? false) === true)
				{
					throw new BadParamsException("Missing required multipart field `{$name}`.");
				}
				continue;
			}

			$value = $data[$name];
			if ($value === null)
			{
				if (($field['required'] ?? false) === true)
				{
					throw new BadParamsException("Multipart field `{$name}` cannot be null.");
				}
				continue;
			}

			$parts[] = $this->createMultipartPart($name, $value, (string)($field['contentType'] ?? ''));
		}

		foreach ($data as $name => $value)
		{
			if (isset($knownFields[$name]) || $value === null)
			{
				continue;
			}

			$parts[] = $this->createMultipartPart((string)$name, $value, '');
		}

		$stream = new MultipartStream($parts);

		return [
			(string)$stream,
			['Content-Type' => 'multipart/form-data; boundary=' . $stream->getBoundary()],
		];
	}

	/**
	 * @return array{0: string, 1: array<string, string>}
	 */
	private function prepareFormBody(mixed $body, string $contentType): array
	{
		$normalized = $this->normalizeBody($body);
		if (!is_array($normalized))
		{
			throw new BadParamsException('Form request body must be an array or object.');
		}

		return [
			http_build_query($normalized),
			['Content-Type' => $contentType],
		];
	}

	/**
	 * @return array{0: string, 1: array<string, string>}
	 */
	private function prepareRawBody(mixed $body, string $contentType): array
	{
		$payload = $this->normalizeRawBody($body);
		$headers = [];
		if ($contentType !== '')
		{
			$headers['Content-Type'] = $contentType;
		}

		return [$payload, $headers];
	}

	/**
	 * @param array<int, array<string, mixed>> $fields
	 * @return array<string, mixed>
	 */
	private function normalizeMultipartBodyData(mixed $body, array $fields): array
	{
		if (is_array($body))
		{
			return $body;
		}

		if (count($fields) === 1 && isset($fields[0]['name']))
		{
			return [(string)$fields[0]['name'] => $body];
		}

		throw new BadParamsException('Multipart request body must be an array or object.');
	}

	/**
	 * @return array{name: string, contents: string, headers?: array<string, string>}
	 */
	private function createMultipartPart(string $name, mixed $value, string $contentType): array
	{
		$part = [
			'name' => $name,
			'contents' => $this->normalizeMultipartValue($value),
		];

		if ($contentType !== '')
		{
			$part['headers'] = ['Content-Type' => $contentType];
		}

		return $part;
	}

	private function normalizeRawBody(mixed $value): string
	{
		if ($value instanceof BackedEnum)
		{
			return (string)$value->value;
		}
		if (is_string($value))
		{
			return $value;
		}
		if (is_int($value) || is_float($value))
		{
			return (string)$value;
		}
		if (is_bool($value))
		{
			return $value ? '1' : '0';
		}

		throw new BadParamsException('Raw request body must be a scalar or stringable enum value.');
	}

	private function normalizeMultipartValue(mixed $value): string
	{
		if ($value instanceof BackedEnum)
		{
			return (string)$value->value;
		}
		if (is_string($value))
		{
			return $value;
		}
		if (is_int($value) || is_float($value))
		{
			return (string)$value;
		}
		if (is_bool($value))
		{
			return $value ? '1' : '0';
		}

		try
		{
			$payload = json_encode($this->normalizeBody($value), JSON_THROW_ON_ERROR);
		}
		catch (JsonException $exception)
		{
			throw new BadParamsException('Multipart field could not be encoded as JSON.', 0, $exception);
		}

		if (!is_string($payload))
		{
			throw new BadParamsException('Multipart field could not be encoded as JSON.');
		}

		return $payload;
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

	/**
	 * @param array<string, scalar|BackedEnum|null> $headers
	 * @return array<string, string>
	 */
	private function normalizeHeaders(array $headers): array
	{
		$result = [];

		foreach ($headers as $name => $value)
		{
			$name = trim((string)$name);
			if ($name === '' || $value === null)
			{
				continue;
			}
			if ($value instanceof BackedEnum)
			{
				$value = $value->value;
			}
			if (!is_scalar($value))
			{
				throw new BadParamsException("Header `{$name}` must be a scalar value.");
			}

			$result[$name] = (string)$value;
		}

		return $result;
	}
	private function formatTransportMessage(string $method, string $url, string $message): string
	{
		$prefix = strtoupper($method) . " {$url} failed";
		$message = trim($message);

		return $message === '' ? $prefix . '.' : $prefix . ': ' . $message;
	}

	private function createHttpException(
		ResponseInterface $response,
		string $method,
		string $url,
		?\Throwable $previous = null
	): HttpException
	{
		$statusCode = $response->getStatusCode();
		$reasonPhrase = $response->getReasonPhrase();
		$responseBody = (string)$response->getBody();
		$message = $this->formatHttpMessage($method, $url, $statusCode, $reasonPhrase, $responseBody);
		$method = strtoupper($method);
		$headers = $response->getHeaders();

		return match (true)
		{
			$statusCode === 401 => new UnauthorizedException(
				$message,
				$statusCode,
				$method,
				$url,
				$headers,
				$responseBody,
				$reasonPhrase,
				$previous
			),
			$statusCode === 403 => new ForbiddenException(
				$message,
				$statusCode,
				$method,
				$url,
				$headers,
				$responseBody,
				$reasonPhrase,
				$previous
			),
			$statusCode >= 400 && $statusCode < 500 => new ClientException(
				$message,
				$statusCode,
				$method,
				$url,
				$headers,
				$responseBody,
				$reasonPhrase,
				$previous
			),
			$statusCode >= 500 => new ServerException(
				$message,
				$statusCode,
				$method,
				$url,
				$headers,
				$responseBody,
				$reasonPhrase,
				$previous
			),
			default => new HttpException(
				$message,
				$statusCode,
				$method,
				$url,
				$headers,
				$responseBody,
				$reasonPhrase,
				$previous
			),
		};
	}

	private function formatHttpMessage(
		string $method,
		string $url,
		int $statusCode,
		string $reasonPhrase,
		string $responseBody
	): string
	{
		$message = strtoupper($method) . " {$url} returned HTTP {$statusCode}";
		if ($reasonPhrase !== '')
		{
			$message .= " {$reasonPhrase}";
		}

		$bodyPreview = $this->summarizeBody($responseBody);
		if ($bodyPreview !== '')
		{
			$message .= ': ' . $bodyPreview;
		}
		else
		{
			$message .= '.';
		}

		return $message;
	}

	private function summarizeBody(string $responseBody): string
	{
		$body = trim($responseBody);
		if ($body === '')
		{
			return '';
		}

		$body = preg_replace('/\s+/', ' ', $body) ?? $body;
		if (strlen($body) > 240)
		{
			return substr($body, 0, 237) . '...';
		}

		return $body;
	}
}
