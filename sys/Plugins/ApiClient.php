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
 * Client for api connections to distribution node
 *
 * @package Environet\Sys\Plugins
 * @author  Ádám Bálint <adam.balint@srg.hu>
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
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {
		$this->apiAddress = $config['apiAddress'];
		$this->apiUsername = $config['apiUsername'];
		$this->privateKeyPath = $config['privateKeyPath'];
	}


	/**
	 * @param SimpleXMLElement $payload
	 *
	 * @return Response
	 * @throws HttpClientException
	 * @throws Exception
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
	 * @param SimpleXMLElement $payload
	 *
	 * @return Request
	 * @throws Exception
	 */
	private function requestFromPayload(SimpleXMLElement $payload): Request {
		$request = new Request(rtrim($this->apiAddress, '/') . '/upload');
		$request->setMethod('POST')->setBody($payload->asXML());

		$request->addHeader('Accept', 'application/json');
		//Add generated auth header with signature
		$request->addHeader('Authorization', $this->generateSignatureHeader($payload, $this->apiUsername));

		return $request;
	}


	/**
	 * @param SimpleXMLElement $xml
	 * @param string           $username
	 *
	 * @return string
	 * @throws Exception
	 */
	private function generateSignatureHeader(SimpleXMLElement $xml, string $username): string {
		if (!file_exists(SRC_PATH . '/conf/plugins/credentials/' . $this->privateKeyPath)) {
			throw new Exception('Test private key at ' . $this->privateKeyPath . ' doesn\'t exist');
		}
		$pkiLib = new PKI();
		$signature = $pkiLib->generateSignature(md5($xml->asXML()), file_get_contents(SRC_PATH . '/conf/plugins/credentials/' . $this->privateKeyPath));

		return $pkiLib->authHeaderWithSignature($signature, $username);
	}


	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return 'MPointPropertyXmlInput generator';
	}


}
