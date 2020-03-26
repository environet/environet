<?php

namespace Environet\Sys\Plugins;

use Environet\Sys\Plugins\Parsers\Parser;
use Environet\Sys\Plugins\Transports\Transport;
use Environet\Sys\Plugins\XmlGenerators\XmlGenerator;
use Environet\Sys\General\HttpClient\Exceptions\HttpClientException;

class Plugin {

	/** @var TransportInterface */
	public $transport;

	/** @var ParserInterface */
    public $parser;

	/** @var XmlGeneratorInterface */
    public $xmlGenerator;

	/** @var ApiClientInterface */
    public $apiClient;


	public function run() {
		$rawData = $this->transport->get();
		$parsedData = $this->parser->parse($rawData);
		$xmlPayload = $this->xmlGenerator->generateXml($parsedData);
		try {
            echo $this->apiClient->upload($xmlPayload)->getStatusCode();
			echo $this->apiClient->upload($xmlPayload)->getBody();
			return 'Upload finished.';
		} catch (HttpClientException $e) {
			return 'Upload error: ' . $e->getMessage();
		}
	}
}
