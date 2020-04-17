<?php


namespace Environet\Sys\General\HttpClient;

use Environet\Sys\General\HttpClient\Exceptions\HttpClientException;
use Environet\Sys\General\HttpClient\Response;

/**
 * Class HttpClient
 *
 * A simple class for making HTTP requests, and return responses
 *
 * @package Environet\Sys\General\HttpClient
 * @author  SRG Group <dev@srg.hu>
 */
class HttpClient {

	/**
	 * Default options
	 *
	 * Available options:
	 *  - timeout: timeout in milliseconds. If 0, it's unlimited
	 *  - verify: boolean value to control verify-peer and verify-host curl options
	 *  - curl: an array of other standard curl options
	 * @var array
	 */
	private $options = [
		'timeout' => 0,
		'verify'  => true
	];


	/**
	 * Send a request with the given options.
	 *
	 * @param Request $request Request object which container URL, and other options
	 * @param array   $options Curl, and other request options.
	 *
	 * @return Response
	 * @throws HttpClientException
	 * @uses \curl_exec()
	 * @uses \Environet\Sys\General\HttpClient\HttpClient::prepare()
	 * @uses \Environet\Sys\General\HttpClient\HttpClient::parseError()
	 */
	public function sendRequest(Request $request, array $options = []): Response {
		// Merge options with default values
		$this->options = array_merge($this->options, $options);

		// Init curl resource
		$curl = $curl = curl_init();

		if (false === $curl) {
			throw new HttpClientException('Unable to create a new cURL handle');
		}

		// Prepare the request.
		// Returned response is not a complete response, just an object. It will be filled with body and header after curl_exec
		$response = $this->prepare($curl, $request, $options);

		// Execute curl resource
		curl_exec($curl);

		// Create exceptions from curl error
		$this->parseError(curl_errno($curl), $curl);

		// Destroy resource
		curl_close($curl);

		// Return the complete response object
		return $response;
	}


	/**
	 * Prepares a cURL resource to send a request.
	 *
	 * @param resource $curl
	 * @param Request  $request
	 * @param array    $options See options property
	 *
	 * @return Response
	 * @uses \curl_setopt()
	 * @uses \curl_setopt_array()
	 * @uses \Environet\Sys\General\HttpClient\HttpClient::processOptions()
	 * @uses \Environet\Sys\General\HttpClient\HttpClient::setOptionsFromRequest()
	 */
	protected function prepare($curl, Request $request, array $options): Response {
		// Set some default cURL options
		if (defined('CURLOPT_PROTOCOLS')) {
			curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
			curl_setopt($curl, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		}

		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($curl, CURLOPT_FAILONERROR, false);

		// Add cURL options based on options array
		$this->processOptions($curl, $this->options);

		// Add cURL options based on request properties
		$this->setOptionsFromRequest($curl, $request);

		// Create an empty response object. Body and header will be added later in callbacks
		$response = new Response();

		// Callback which handles headers (status code, and other headers)
		curl_setopt($curl, CURLOPT_HEADERFUNCTION, function ($ch, $data) use ($response) {
			$str = trim($data);
			if ('' !== $str) {
				if (0 === strpos(strtolower($str), 'http/')) {
					//Set status -> status code
					$response->setRawStatus($str);
				} else {
					//Set other headers
					$response->addRawHeader($str);
				}
			}

			return strlen($data);
		});

		// Callback which writes body to response object
		curl_setopt($curl, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($response) {
			$response->setBody($data);

			return strlen($data);
		});

		// Apply additional options. It can override any previously added cURL options
		curl_setopt_array($curl, $options['curl'] ?? []);

		return $response;
	}


	/**
	 * Sets options on a cURL resource based on a request.
	 *
	 * @param resource $curl    A cURL resource
	 * @param Request  $request A request object
	 *
	 * @uses \curl_setopt_array()
	 */
	private function setOptionsFromRequest($curl, Request $request): void {
		$options = [
			CURLOPT_CUSTOMREQUEST => $request->getMethod(), //HTTP method
			CURLOPT_URL           => $request->getUri(), //URL
			CURLOPT_HTTPHEADER    => $request->getCurlHeaders(), //Request headers
			CURLOPT_HTTP_VERSION  => CURL_HTTP_VERSION_1_1
		];

		// Set properties based on method type
		switch (strtoupper($request->getMethod())) {
			case 'HEAD':
				$options[CURLOPT_NOBODY] = true;
				break;
			case 'GET':
				$options[CURLOPT_HTTPGET] = true;
				break;
			case 'POST':
			case 'PUT':
			case 'DELETE':
			case 'PATCH':
			case 'OPTIONS':
				$options[CURLOPT_POSTFIELDS] = (string) $request->getBody();
		}

		// Set options array
		curl_setopt_array($curl, $options);
	}


	/**
	 * Set options on a cURL resource based on custom options
	 *
	 * @param resource $curl
	 * @param array    $options
	 *
	 * @uses \curl_setopt()
	 */
	private function processOptions($curl, array $options): void {
		// Verify-host and peer options
		$verify = $options['verify'] ?? true;
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $verify ? 0 : 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);

		// Set timeout
		if (!empty($options['timeout'])) {
			curl_setopt($curl, CURLOPT_TIMEOUT, $options['timeout']);
		}
	}


	/**
	 * Map cURL errors to Exceptions
	 *
	 * @param int      $errno
	 * @param resource $curl
	 *
	 * @throws HttpClientException
	 */
	protected function parseError(int $errno, $curl): void {
		switch ($errno) {
			case CURLE_OK:
				// All OK, create a response object
				break;
			case CURLE_COULDNT_RESOLVE_PROXY:
			case CURLE_COULDNT_RESOLVE_HOST:
			case CURLE_COULDNT_CONNECT:
			case CURLE_OPERATION_TIMEOUTED:
			case CURLE_SSL_CONNECT_ERROR:
				throw new HttpClientException("CURL connetion error #$errno: " . curl_error($curl));
			case CURLE_ABORTED_BY_CALLBACK:
				throw new HttpClientException("CURL request aborted #$errno: " . curl_error($curl));
			default:
				throw new HttpClientException("CURL error #$errno " . curl_error($curl));
		}
	}


}
