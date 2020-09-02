<?php


namespace Environet\Sys\General\HttpClient;

/**
 * Class Response
 *
 * Class of HTTP-client responses
 *
 * @package Environet\Sys\General\HttpClient
 * @author  SRG Group <dev@srg.hu>
 */
class Response {

	/**
	 * @var int HTTP Status code
	 */
	protected $statusCode;

	/**
	 * @var string Raw body string
	 */
	private $body;

	/**
	 * @var array Array of HTTP headers. Keys are the header names, values are the header values
	 */
	private $headers;


	/**
	 * Response constructor.
	 *
	 * @param int    $statusCode HTTP status code
	 * @param string $body       Raw body
	 * @param array  $headers    Array of HTTP headers
	 */
	public function __construct(int $statusCode = null, string $body = null, array $headers = null) {
		if (!is_null($statusCode)) {
			$this->statusCode = $statusCode;
		}
		if (!is_null($body)) {
			$this->body = $body;
		}
		if (!is_null($headers)) {
			$this->headers = $headers;
		}
	}


	/**
	 * Get HTTP Status code
	 *
	 * @return int
	 */
	public function getStatusCode(): int {
		return $this->statusCode;
	}


	/**
	 * Set HTTP status code of response
	 *
	 * @param int $statusCode
	 *
	 * @return Response
	 */
	public function setStatusCode(int $statusCode): Response {
		$this->statusCode = $statusCode;

		return $this;
	}


	/**
	 * Parse the raw HTTP status header, and set the numberic status code
	 *
	 * @param string $status
	 *
	 * @return $this
	 * @uses \Environet\Sys\General\HttpClient\Response::setStatusCode()
	 */
	public function setRawStatus(string $status) {
		if (preg_match('/[2-5]\d{2}/', $status, $match)) {
			$this->setStatusCode($match[0]);
		}

		return $this;
	}


	/**
	 * Does the response have a body?
	 *
	 * @return bool
	 */
	public function hasBody(): bool {
		return (bool) $this->body;
	}


	/**
	 * Get raw body string
	 *
	 * @return string
	 */
	public function getBody(): ?string {
		return $this->body;
	}


	/**
	 * Set raw body string
	 *
	 * @param string $body
	 *
	 * @return Response
	 */
	public function setBody(string $body): Response {
		$this->body = $body;

		return $this;
	}
	
	
	/**
	 * Append to raw body string
	 *
	 * @param string $body
	 *
	 * @return Response
	 */
	public function appendBody(string $data): string {
		$this->body .= $data;
		
		return $this->body;
	}


	/**
	 * Get array of headers.
	 *
	 * @return array
	 * @see Response::$headers
	 */
	public function getHeaders(): array {
		return $this->headers;
	}


	/**
	 * Add a raw header the headers array as key => value pair
	 *
	 * @param string $header
	 *
	 * @return Response
	 * @see Response::$headers
	 */
	public function addRawHeader(string $header): Response {
		$array = explode(':', $header);
		if (count($array) === 2) {
			//It's a valid header format
			$this->headers[trim($array[0])] = trim($array[1]);
		}

		return $this;
	}


}
