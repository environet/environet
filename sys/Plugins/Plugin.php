<?php

namespace Environet\Sys\Plugins;

use Environet\Sys\Commands\Console;

class Plugin {

	/** @var TransportInterface */
	public $transport;

	/** @var ParserInterface */
	public $parser;

	/** @var ApiClientInterface */
	public $apiClient;


	public function run(Console $console) {
		$console->writeLine('Running plugin', '36');
		$resources = $this->transport->get();

		$successful = 0;
		$failed = 0;

		foreach ($resources as $resource) {
			$console->writeLine("Uploading $resource->name", Console::COLOR_YELLOW);
			$xmls = $this->parser->parse($resource->contents);


			foreach ($xmls as $xmlPayload) {
				$console->write('Uploading monitoring point data', Console::COLOR_YELLOW);
				try {
					$apiResponse = $this->apiClient->upload($xmlPayload);
					$apiResponse->getBody() ? $console->writeLine($apiResponse->getBody()) : '';
					$console->write("\r");
					$console->writeLine("Monitoring point data upload successful  ", Console::COLOR_GREEN);
					$successful++;
				} catch (\Exception $e) {
					$console->write("\r");
					$console->writeLine('Upload failed, response:                ', Console::COLOR_RED);
					$console->writeLine($e->getMessage(), Console::COLOR_RED);
					$failed++;
				}
			}
		}

		$console->writeLine('');
		$console->writeLine("Successful requests: $successful", Console::COLOR_GREEN);
		$console->writeLine("Failed requests: $failed", Console::COLOR_RED);
	}


}
