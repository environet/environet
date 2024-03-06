<?php

namespace Environet\Sys\Plugins;

use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\Parsers\XmlParser;
use Environet\Sys\Plugins\Parsers\XmlParserOld;

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
	 * @param string  $configFile
	 */
	public function run(Console $console, string $configFile) {
		$console->writeLog('Running plugin ----------------------------------------------------------------------------------------------', true, true);
		$console->writeLogNoEol('');    // to prefix data to following message
		try {
			$resources = $this->transport->get($console, $configFile);
		} catch (\Exception $e) {
			$console->writeLog($e->getMessage(), true, true);
			$resources = [];
		}

		if (count($resources) < 1) {
			$console->writeLine('Nothing to upload');
		} else {
			$successful = 0;
			$failed = 0;
			$successfulDownloads = 0;
			$failedDownloads = 0;

			$allNCDs = [];

			foreach ($resources as $resource) {
				try {
					if ($resource->getPointNCDs()) {
						$allNCDs = array_unique(array_merge($resource->getPointNCDs()));
					}
					$console->writeLog(sprintf("Downloaded %s", $resource->getName()));

					$xmls = $this->parser->parse($resource);

					$successfulDownloads ++;

					$payloadStorage = SRC_PATH . '/data/data_node_payloads';
					if (!is_dir($payloadStorage)) {
						mkdir($payloadStorage, 0755, true);
					}
					foreach ($xmls as $xmlPayload) {
						$ns = $xmlPayload->getNamespaces(true);
						$child = $xmlPayload->children($ns['environet']);
						$xmlMPointId = $child->attributes()['MonitoringPointId'];

						// remove current monitoring point id from list
						if (($key = array_search($xmlMPointId, $allNCDs)) !== false) {
							unset($allNCDs[$key]);
						}

						$console->writeLogNoEol('Uploading monitoring point data for station NCD ' . $xmlMPointId . ": ");
						//$console->write($xmlPayload->asXML(), Console::COLOR_YELLOW);
						try {
							$this->apiClient->upload($xmlPayload);
							$console->writeLine('success');
							$successful ++;
						} catch (\Exception $e) {
							$filename = $payloadStorage . '/' . date('YmdHis') . '_' . $xmlMPointId . '.xml';
							file_put_contents($filename, $xmlPayload->asXML());

							$console->writeLine('failed');
							$console->writeLog(sprintf("Upload for station NCD %s failed, response: ", $xmlMPointId), true);
							$console->writeLog($e->getMessage(), true);
							$console->writeLog('Payload stored: ' . ltrim(str_replace(SRC_PATH, '', $filename), '/'), true);
							$console->writeLog('---', true);
							$failed ++;
						}
					}
				} catch (\Exception $e) {
					$console->writeLog(
						sprintf(
							"Parsing of %s (first 100 characters: \"%s\") failed, response: %s",
							$resource->getName(),
							$this->previewString($resource->getContents(), 100),
							$e->getMessage()
						),
						true,
						true
					);
					$failedDownloads ++;
				}
			}


			$missingMonitoringPoints = count($allNCDs);

			$thereWasAnError = false;
			if ($failedDownloads > 0 || $failed > 0 || $missingMonitoringPoints > 0) {
				$thereWasAnError = true;
			}
			$console->writeLog("Successful downloads from data provider: $successfulDownloads");
			$console->writeLog("Successful uploads to distribution node: $successful");
			$console->writeLog("Failed downloads from data provider: $failedDownloads", $thereWasAnError, $thereWasAnError);
			$console->writeLog("Failed uploads to distribution node: $failed", $thereWasAnError, $thereWasAnError);
			$console->writeLog("Monitoring points missing in data: " . $missingMonitoringPoints, $thereWasAnError, $thereWasAnError);
			if (count($allNCDs) > 0) {
				$console->writeLog("Following monitoring points missing in data: " . implode(" ", $allNCDs), true);
			}
		}
		$console->writeLog('Running plugin finished -------------------------------------------------------------------------------------', true, true);
	}


	/**
	 * Give a preview of a string (example of an xml file)
	 *
	 * @param string $data
	 * @param int    $lengthOfPreview
	 */
	public function previewString(string $data, int $lengthOfPreview) {
		$a = str_replace(["\r", "\n"], "\\n", $data);

		return substr($a, 0, $lengthOfPreview);
	}


}
