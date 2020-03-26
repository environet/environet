<?php

namespace Environet\Sys\Plugins;

use Environet\Sys\Commands\Console;
use Environet\Sys\General\HttpClient\Exceptions\HttpClientException;

class Plugin {

	/** @var TransportInterface */
	public $transport;

	/** @var ParserInterface */
    public $parser;

	/** @var ApiClientInterface */
    public $apiClient;


	public function run(Console $console) {
        $console->writeLine('Running plugin', '36');
        $data = $this->transport->get();

		foreach ($data as $i => $rawData) {
            $parsedData = $this->parser->parse($rawData);

            foreach ($parsedData as $xmlPayload) {

                //echo $xmlPayload->asXML();
                try {
                    $apiResponse = $this->apiClient->upload($xmlPayload);
                    $console->writeLine($apiResponse->getStatusCode()) ;
                    $apiResponse->getBody() ? $console->writeLine($apiResponse->getBody()) : '';
                    $console->writeLine('Upload OK', '33');
                    //return 'Upload finished.';
                } catch (\Exception $e) {
                    $console->writeLine($e->getMessage(), Console::COLOR_RED);
                    //return 'Upload error: ' . $e->getMessage();
                }
            }
        }

	}
}
