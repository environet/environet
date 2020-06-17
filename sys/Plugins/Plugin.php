<?php

namespace Environet\Sys\Plugins;

use Environet\Sys\Commands\Console;

/**
 * Class Plugin
 *
 * Utility class for uploading a series of resources to a distribution node with the help of a console.
 *
 * @package Environet\Sys\Plugins
 * @author  SRG Group <dev@srg.hu>
 */
class Plugin {

	/** @var TransportInterface The used transport layer */
	public $transport;

	/** @var ParserInterface The used parser */
	public $parser;

	/** @var ApiClientInterface The used API client */
	public $apiClient;


	/**
	 * Run the plugin console command.
	 *
	 * @param Console $console
	 */
	public function run(Console $console) {
		$console->writeLine('Running plugin', '36');
		$resources = $this->transport->get();
		
		if(count($resources) < 1) {
			$console->writeLine('Nothing to upload', Console::COLOR_YELLOW);
			return;
		}

		$successful = 0;
		$failed = 0;

		foreach ($resources as $resource) {
			$console->writeLine("Uploading $resource->name", Console::COLOR_YELLOW);
			$xmls = $this->parser->parse($resource->contents);
			$console->writeLine('');
			foreach ($xmls as $xmlPayload) {
				$console->write('Uploading monitoring point data', Console::COLOR_YELLOW);
				try {
					$apiResponse = $this->apiClient->upload($xmlPayload);
					$console->write("\r");
					$console->writeLine('Monitoring point data upload successful  ', Console::COLOR_GREEN);
					$successful ++;
				} catch (\Exception $e) {
					$console->write("\r");
					$console->writeLine('Upload failed, response:                ', Console::COLOR_RED);
					$console->writeLine($e->getMessage(), Console::COLOR_RED);
					// $console->writeLine('Request xml:');
					// $console->writeLine($xmlPayload->asXML());
					$failed ++;
				}
			}
		}

		$console->writeLine('');
		$console->writeLine("Successful requests: $successful", Console::COLOR_GREEN);
		$console->writeLine("Failed requests: $failed", Console::COLOR_RED);
	}


}
