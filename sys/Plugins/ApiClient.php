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
		$console->writeLine("Configuring API client", Console::COLOR_YELLOW);

		$console->writeLine("Enter the address of the distribution node", Console::COLOR_YELLOW);
		$apiAddress = $console->askWithDefault('API host:', 'https://environet.environ.hu/');

		$console->writeLine("Enter a username to use for uploading the data", Console::COLOR_YELLOW);
		$apiUsername = $console->ask("API username:");

		$console->writeLine("Enter the path to the private key to be used for authenticating requests to the distribution node. This should be a path relative to '/conf/plugins/credentials'.", Console::COLOR_YELLOW);
		$console->writeLine("For example: If you placed your private key into conf/plugins/credentials/privatekey.pem, you would enter 'privatekey.pem'", Console::COLOR_YELLOW);
		$privateKeyPath = $console->ask("API private key path:");

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
			throw new Exception("Private key at {$this->privateKeyPath} doesn't exist");
		}
		$pkiLib = new PKI();
		$signature = $pkiLib->generateSignature(md5($xml->asXML()), file_get_contents(SRC_PATH . '/conf/plugins/credentials/' . $this->privateKeyPath));

		return $pkiLib->authHeaderWithSignature($signature, $username);
	}


	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return 'default api client';
	}


	/**
	 * @inheritDoc
	 */
	public static function getHelp(): string {
		return '';
	}


}
