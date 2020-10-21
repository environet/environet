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
		
		if (count($resources) < 1) {
			$console->writeLine('Nothing to upload', Console::COLOR_YELLOW);
			return;
		}

		$successful = 0;
		$failed = 0;
		$successfulDownloads = 0;
		$failedDownloads = 0;

		foreach ($resources as $resource) {
			try {
				$console->writeLine("Uploading $resource->name", Console::COLOR_YELLOW);

				$xmls = $this->parser->parse($resource);
				if (!count($xmls)) {
					$console->writeLine("Couldn't parse $resource->name into xml", Console::COLOR_RED);
				}
				$successfulDownloads++;
	
				foreach ($xmls as $xmlPayload) {
					$console->write('Uploading monitoring point data', Console::COLOR_YELLOW);
					//$console->write($xmlPayload->asXML(), Console::COLOR_YELLOW);
					try {
						$this->apiClient->upload($xmlPayload);
						$console->write("\r");
						$console->writeLine('Monitoring point data upload successful  ', Console::COLOR_GREEN);
						$console->writeLine('');

						$successful ++;
					} catch (\Exception $e) {
						$console->write("\r");
						$console->writeLine('Upload failed, response:                ', Console::COLOR_RED);
						$console->writeLine($e->getMessage(), Console::COLOR_RED);
						$console->writeLine('Payload was ', Console::COLOR_YELLOW);
						$console->writeLine($xmlPayload->asXML());
						$console->writeLine('');
						$failed ++;
					}
				}
			} catch (\Exception $e) {
				$console->write("\r");
				$console->write('Parsing failed, response: ', Console::COLOR_RED);
				$console->writeLine($e->getMessage(), Console::COLOR_RED);
				$failedDownloads++;
			}
		}

		$console->writeLine('');
		$console->writeLine("Successful downloads from data provider: $successfulDownloads", Console::COLOR_GREEN);
		$console->writeLine("Successful uploads to distribution node: $successful", Console::COLOR_GREEN);
		$console->writeLine("Failed downloads from data provider: $failedDownloads", Console::COLOR_RED);
		$console->writeLine("Failed uploads to distribution nodes: $failed", Console::COLOR_RED);
	}


}
