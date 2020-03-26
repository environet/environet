<?php

namespace Environet\Sys\General;

/**
 * Class Request
 *
 * A helper class for easily getting information from the HTTP request.
 *
 * @method bool isGet() True if request method is GET
 * @method bool isPost() True if request method is POST
 * @method bool isPut() True if request method is PUT
 * @method bool isPatch() True if request method is PATCH
 * @method bool isDelete() True if request method is DELETE
 *
 * @package Environet\Sys\General
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class Request {

	const PREFIX_ADMIN = 'admin';
	const PREFIX_UPLOAD = 'upload';
	const PREFIX_DOWNLOAD = 'download';

	/**
	 * @var string|null
	 */
	protected $path;

	/**
	 * @var string|null
	 */
	protected $query;

	/**
	 * @var array
	 */
	protected $pathParts = [];

	/**
	 * @var Identity|null Identity of the request
	 */
	protected $identity;

	/**
	 * Parsed query string
	 * @var array|null
	 */
	protected $parsedQuery;


	/**
	 * Create a route and parse the url to path and query
	 *
	 * Request constructor.
	 */
	public function __construct() {
		$requestUri = $_SERVER['REQUEST_URI'] ?? null;

		//Parse url
		$this->path = parse_url($requestUri, PHP_URL_PATH);
		$this->query = parse_url($requestUri, PHP_URL_QUERY);

		//Create path parts
		$this->pathParts = explode('/', trim($this->path, '/'));
		parse_str($this->query, $this->parsedQuery);
	}


	/**
	 * Check if route is an admin route (/admin prefix)
	 *
	 * @return bool
	 */
	public function isAdmin() {
		return !empty($this->pathParts[0]) && $this->pathParts[0] === self::PREFIX_ADMIN;
	}


	/**
	 * Check if route is an upload route (/upload prefix)
	 *
	 * @return bool
	 */
	public function isUpload() {
		return !empty($this->pathParts[0]) && $this->pathParts[0] === self::PREFIX_UPLOAD;
	}


	/**
	 * Check if route is an upload route (/upload prefix)
	 *
	 * @return bool
	 */
	public function isDownload() {
		return !empty($this->pathParts[0]) && $this->pathParts[0] === self::PREFIX_DOWNLOAD;
	}


	/**
	 * Get path string of the request
	 *
	 * @return string|null
	 */
	public function getPath(): ?string {
		return $this->path;
	}


	/**
	 * Get the query part of the request
	 *
	 * @return string|null
	 */
	public function getQuery(): ?string {
		return $this->query;
	}


	/**
	 * Get value of a query string param
	 *
	 * @param string $paramName
	 * @param null   $default
	 *
	 * @return mixed|null
	 */
	public function getQueryParam(string $paramName, $default = null) {
		return $this->parsedQuery[$paramName] ?? $default;
	}


	/**
	 * Get values from query string
	 *
	 * @return mixed|null
	 */
	public function getQueryParams() {
		return $this->parsedQuery;
	}


	/**
	 * Get the method of the request
	 *
	 * Get request method
	 *
	 * @return mixed|null
	 */
	public function getMethod(): ?string {
		return $_SERVER['REQUEST_METHOD'] ?? null;
	}


	/**
	 * Get exploded path string
	 *
	 * @return array
	 */
	public function getPathParts(): array {
		return $this->pathParts;
	}


	/**
	 * Get the identity attached to this request
	 *
	 * @return Identity|null
	 */
	public function getIdentity(): ?Identity {
		return $this->identity;
	}


	/**
	 * Sets and identity instance
	 *
	 * @param Identity|null $identity
	 *
	 * @return self
	 */
	public function setIdentity(?Identity $identity): self {
		$this->identity = $identity;
		return $this;
	}


	/**
	 * Magic method for HTTP method checks (isPost, isGet, etc)
	 *
	 * @param string $name Name of the method
	 * @param mixed $arguments Arguments
	 *
	 * @return bool
	 */
	public function __call($name, $arguments) {
		if (preg_match('/^is([a-zA-Z]+)$/', $name, $match)) {
			return $this->getMethod() === strtoupper($match[1]);
		}
	}


	/**
	 * Return the trimmed post data.
	 *
	 * @return array
	 */
	public function getCleanData() : array {
		$data = $_POST;
		if (empty($data)) {
			return [];
		}

		return arrayMapRecursive($data, 'trim');
	}


	/**
	 * Return the referer URL.
	 *
	 * @return string|null
	 */
	public function getReferer() : ?string {
		return $_SERVER['HTTP_REFERER'] ?? null;
	}


}
