<?php

namespace Environet\Sys\Plugins;

use Environet\Sys\Commands\Console;
use Environet\Sys\General\HttpClient\Exceptions\HttpClientException;
use Environet\Sys\General\HttpClient\HttpClient;
use Environet\Sys\General\HttpClient\Request;
use Environet\Sys\General\HttpClient\Response;
use Environet\Sys\General\PKI;
use Exception;
use SimpleXMLElement;

/**
 * Class ApiClient
 *
 * Client for api connections to a distribution node.
 *
 * @package Environet\Sys\Plugins
 * @author  SRG Group <dev@srg.hu>
 */
class ApiClient implements ApiClientInterface, BuilderLayerInterface {

	/**
	 * @var string Address of distribution node
	 */
	private $apiAddress;

	/**
	 * @var string Username for upload process
	 */
	private $apiUsername;

	/**
	 * @var string Path to private key file
	 */
	private $privateKeyPath;


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console): ApiClient {
		$console->writeLine("Configuring API client");
		$apiAddress = $console->ask("Enter API host:", 200);
		$apiUsername = $console->ask("Enter API username:", 200);
		$privateKeyPath = $console->ask("Enter API private key path:", 200);

		$config = [
			'apiAddress'     => $apiAddress,
			'apiUsername'    => $apiUsername,
			'privateKeyPath' => $privateKeyPath
		];

		return new self($config);
	}


	/**
	 * @return string
	 */
	public function serializeConfiguration(): string {
		$result = '';
		$result .= "apiAddress = $this->apiAddress\n";
		$result .= "apiUsername = $this->apiUsername\n";
		$result .= "privateKeyPath = $this->privateKeyPath\n";

		return $result;
	}


	/**
	 * ApiClient constructor.
	 * Sets API address, username and private key path.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {
		$this->apiAddress = $config['apiAddress'];
		$this->apiUsername = $config['apiUsername'];
		$this->privateKeyPath = $config['privateKeyPath'];
	}


	/**
	 * Upload an XML file to the distribution node.
	 *
	 * @param SimpleXMLElement $payload
	 *
	 * @return Response
	 * @throws HttpClientException
	 * @throws Exception
	 * @uses \Environet\Sys\Plugins\ApiClient::requestFromPayload()
	 * @uses \Environet\Sys\General\HttpClient\HttpClient::sendRequest()
	 */
	public function upload(SimpleXMLElement $payload): Response {
		$request = $this->requestFromPayload($payload);
		$client = new HttpClient();
		$response = $client->sendRequest($request);
		if ($response->getStatusCode() !== 200) {
			throw new Exception($response->getBody());
		}

		return $response;
	}


	/**
	 * Create a request with an XML payload.
	 *
	 * @param SimpleXMLElement $payload
	 *
	 * @return Request
	 * @throws Exception
	 * @uses \Environet\Sys\General\HttpClient\Request
	 * @uses \Environet\Sys\Plugins\ApiClient::generateSignatureHeader()
	 */
	private function requestFromPayload(SimpleXMLElement $payload): Request {
		$request = new Request(rtrim($this->apiAddress, '/') . '/upload');
		$request->setMethod('POST')->setBody($payload->asXML());

		$request->addHeader('Accept', 'application/json');
		// Add generated auth header with signature
		$request->addHeader('Authorization', $this->generateSignatureHeader($payload, $this->apiUsername));

		return $request;
	}


	/**
	 * Generate authorization header information.
	 * The signature is built from the hashed XML data and the given user's private key.
	 *
	 * @param SimpleXMLElement $xml
	 * @param string           $username
	 *
	 * @return string
	 * @throws Exception
	 * @uses \Environet\Sys\General\PKI::generateSignature()
	 * @uses \Environet\Sys\General\PKI::authHeaderWithSignature()
	 */
	private function generateSignatureHeader(SimpleXMLElement $xml, string $username): string {
		$fullPath = SRC_PATH . "/conf/plugins/credentials/{$this->privateKeyPath}";
		if (!file_exists($fullPath)) {
			throw new Exception("Test private key at {$this->privateKeyPath} doesn't exist");
		}
		$pkiLib = new PKI();
		$signature = $pkiLib->generateSignature(md5($xml->asXML()), file_get_contents($fullPath));

		return $pkiLib->authHeaderWithSignature($signature, $username);
	}


	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return 'MPointPropertyXmlInput generator';
	}


}
