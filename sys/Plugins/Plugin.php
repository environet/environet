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
		$console->writeLog('Running plugin ----------------------------------------------------------------------------------------------', true, true);
		$console->writeLogNoEol('');	// to prefix data to following message
		$resources = $this->transport->get($console);

		if (count($resources) < 1) {
			$console->writeLine('Nothing to upload');
		} else {

			$successful = 0;
			$failed = 0;
			$successfulDownloads = 0;
			$failedDownloads = 0;
			$missingMonitoringPoints = 0;

			$MonitoringPointNCDs = [];
			
			foreach ($resources as $resource) {
				try {
					if (is_array($resource->meta['MonitoringPointNCDs'])) {
						$MonitoringPointNCDs = array_merge($MonitoringPointNCDs, $resource->meta['MonitoringPointNCDs']);
						$MonitoringPointNCDs = array_unique($MonitoringPointNCDs);
					}
					$console->writeLog("Downloaded $resource->name");

					$xmls = $this->parser->parse($resource);

					if (!count($xmls)) {
						$console->writeLog("Couldn't parse $resource->name into xml", true);
					}
					$successfulDownloads++;

					foreach ($xmls as $xmlPayload) {
						$ns = $xmlPayload->getNamespaces(true);
						$child = $xmlPayload->children($ns['environet']);

						// remove current monitoring point id from list
						if (($key = array_search($child->MonitoringPointId, $MonitoringPointNCDs)) !== false) {
	 						unset($MonitoringPointNCDs[$key]);
						}

						$console->writeLogNoEol('Uploading monitoring point data for station NCD ' . $child->MonitoringPointId . ": ");
						//$console->write($xmlPayload->asXML(), Console::COLOR_YELLOW);
						try {
							$this->apiClient->upload($xmlPayload);
							$console->writeLine('success');
							$successful ++;
						} catch (\Exception $e) {
							$console->writeLine('failed');
							$console->writeLog('Upload for station NCD ' . $child->MonitoringPointId . ' failed, response: ', true);
							$console->writeLog($e->getMessage(), true);
							$console->writeLog('Payload was ', true);
							$console->writeLog($xmlPayload->asXML(), true);
							$console->writeLog('---', true);
							$failed ++;
						}
					}
				} catch (\Exception $e) {
					$console->writeLog('Parsing of ' . $resource->name . ' failed, response: ', true, true);
					$console->writeLog($e->getMessage(), true, true);
					$failedDownloads++;
				}
			}


			$missingMonitoringPoints = sizeof($MonitoringPointNCDs);

			$thereWasAnError = false;
			if ($failedDownloads > 0 || $failed > 0 || $missingMonitoringPoints > 0) {
				$thereWasAnError = true;
			}
			$console->writeLog("Successful downloads from data provider: $successfulDownloads");
			$console->writeLog("Successful uploads to distribution node: $successful");
			$console->writeLog("Failed downloads from data provider: $failedDownloads", $thereWasAnError, $thereWasAnError);
			$console->writeLog("Failed uploads to distribution node: $failed", $thereWasAnError, $thereWasAnError);
			$console->writeLog("Missing monitoring points: " . $missingMonitoringPoints, $thereWasAnError, $thereWasAnError);
			if (sizeof($MonitoringPointNCDs)>0) {
				$console->writeLog("Following monitoring points missing in data: " . implode(" ", $MonitoringPointNCDs), true);
			}

		}
		$console->writeLog('Running plugin finished -------------------------------------------------------------------------------------', true, true);

	}


}
