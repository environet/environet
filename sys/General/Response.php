<?php

namespace Environet\Sys\General;

/**
 * Class Response
 *
 * A helper class which represents a http response
 *
 * @package Environet\Sys\General
 * @author  SRG Group <dev@srg.hu>
 */
class Response {

	/**
	 * @var string|null The body of the response
	 */
	protected $content = null;

	/**
	 * @var int The status code of the response
	 */
	protected $statusCode = 200;

	/**
	 * @var array Array of header entries
	 */
	protected $headers = [];


	/**
	 * Response constructor.
	 * Sets the content if available.
	 *
	 *
	 * @uses Response::setContent
	 */
	public function __construct(?string $content = null) {
		if (!is_null($content)) {
			$this->setContent($content);
		}
	}


	/**
	 * Get the response body content
	 *
	 */
	public function getContent(): ?string {
		return $this->content;
	}


	/**
	 * Set the body content
	 *
	 *
	 * @return Response
	 */
	public function setContent(?string $content) {
		$this->content = $content;

		return $this;
	}


	/**
	 * Get the HTTP status code
	 */
	public function getStatusCode(): int {
		return $this->statusCode;
	}


	/**
	 * Set the HTTP status code
	 *
	 *
	 */
	public function setStatusCode(int $statusCode): Response {
		$this->statusCode = $statusCode;

		return $this;
	}


	/**
	 * Add a HTTP header as string
	 *
	 *
	 * @return Response
	 */
	public function addHeader(string $header) {
		$this->headers[] = $header;

		return $this;
	}


	/**
	 * Set all headers with an array of strings. It resets all old headers
	 *
	 *
	 */
	public function setHeaders(array $headers): Response {
		$this->headers = $headers;

		return $this;
	}


	/**
	 * Get array of headers
	 *
	 */
	public function getHeaders(): array {
		return $this->headers;
	}


	/**
	 * Get size of response
	 */
	public function getSize(): int {
		return mb_strlen($this->getContent(), '8bit');
	}


	/**
	 * Return the body content, and set all headers + status code
	 */
	public function __toString() {
		foreach ($this->headers as $header) {
			header($header);
		}
		http_response_code($this->statusCode);

		return $this->content ?: '';
	}


	/**
	 * Creates a http redirect response with the given url
	 *
	 *
	 * @return Response
	 * @uses Response::addHeader
	 * @uses Response::setStatusCode
	 */
	public function redirect(string $to, int $statusCode = 302) {
		$this->addHeader('Location: ' . $to);
		$this->setStatusCode($statusCode);

		return $this;
	}


}
