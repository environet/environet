<?php


namespace Environet\Sys\General\HttpClient;

/**
 * Class Request
 *
 * Wrapper class for storing request information.
 *
 * @package Environet\Sys\General\HttpClient
 * @author  SRG Group <dev@srg.hu>
 */
class Request {

	/**
	 * Request method
	 * @var string
	 */
	protected $method = 'GET';

	/**
	 * Request URI
	 * @var string
	 */
	protected $uri;

	/**
	 * Request headers.
	 * All item is an array with 'name' and 'value' keys. This structure allows multiple headers with the same name.
	 *
	 * @var array
	 */
	protected $headers = [];

	/**
	 * Body for POST, PUT requests
	 *
	 * @var string
	 */
	protected $body = null;


	/**
	 * Request constructor.
	 *
	 * @param string      $uri    Request uri
	 * @param string|null $method HTTP method
	 */
	public function __construct(string $uri, string $method = null) {
		$this->uri = $uri;
		if (!is_null($method)) {
			$this->method = $method;
		}
	}


	/**
	 * Set HTTP method
	 * @return string
	 */
	public function getMethod(): string {
		return $this->method;
	}


	/**
	 * Get HTTP method
	 *
	 * @param string $method
	 *
	 * @return Request
	 */
	public function setMethod(string $method): Request {
		$this->method = $method;

		return $this;
	}


	/**
	 * Overwrite the URI
	 *
	 * @return string
	 */
	public function getUri(): string {
		return $this->uri;
	}


	/**
	 * Get the request URI
	 *
	 * @param string $uri
	 *
	 * @return Request
	 */
	public function setUri(string $uri): Request {
		$this->uri = $uri;

		return $this;
	}


	/**
	 * Return the array of headers.
	 * See structure in property definition: {@see Request::$headers}.
	 *
	 * @return array
	 */
	public function getHeaders(): array {
		return $this->headers;
	}


	/**
	 * Get headers in a cURL-compatible format
	 *
	 * @return array
	 */
	public function getCurlHeaders(): array {
		return array_map(function (array $header) {
			return $header['name'] . ': ' . $header['value'];
		}, $this->headers);
	}


	/**
	 * Add a single header to the headers array
	 *
	 * @param string $name  Header name
	 * @param string $value Header value
	 *
	 * @return Request
	 */
	public function addHeader(string $name, string $value) {
		$this->headers[] = [
			'name'  => $name,
			'value' => $value
		];

		return $this;
	}


	/**
	 * Get the request body
	 *
	 * @return string
	 */
	public function getBody(): string {
		return $this->body;
	}


	/**
	 * Set a request body
	 *
	 * @param string $body
	 *
	 * @return Request
	 */
	public function setBody(string $body): Request {
		$this->body = $body;

		return $this;
	}


}
