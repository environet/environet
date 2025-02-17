<?php

namespace Environet\Sys\Plugins;

use Environet\Sys\Commands\Console;
use SimpleXMLElement;

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
		$console->setDatePrefix();
		$console->writeLine('Running plugin ----------------------------------------------------------------------------------------------', null, null, true, true);
		$console->write('');    // to prefix data to following message
		try {
			$resources = $this->transport->get($console, $configFile);
		} catch (\Exception $e) {
			$console->writeLine($e->getMessage(), null, null, true, true);
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
				/** @var Resource $resource */
				try {
					if ($resource->getPointNCDs()) {
						$allNCDs = array_unique(array_merge($resource->getPointNCDs()));
					}
					$console->writeLine(sprintf("Downloaded %s", $resource->getName()));

					$xmls = $this->parser->parse($resource);

					$successfulDownloads ++;

					$payloadStorage = SRC_PATH . '/data/data_node_payloads';
					if (!is_dir($payloadStorage)) {
						mkdir($payloadStorage, 0755, true);
					}

					$resourceSuccess = true;
					foreach ($xmls as $xmlPayload) {
						$ns = $xmlPayload->getNamespaces(true);
						$child = $xmlPayload->children($ns['environet']);
						$xmlMPointId = $child->MonitoringPointId->__toString();

						// remove current monitoring point id from list
						if (($key = array_search($xmlMPointId, $allNCDs)) !== false) {
							unset($allNCDs[$key]);
						}

						$console->write('Uploading monitoring point data for station NCD ' . $xmlMPointId . ": ");
						//$console->write($xmlPayload->asXML(), Console::COLOR_YELLOW);
						try {
							$response = $this->apiClient->upload($xmlPayload);
							$console->writeLine('success');

							try {
								//Fetch response XML and log messages if any
								$responseXml = new SimpleXMLElement($response->getBody());
								foreach ($responseXml->xpath('/environet:UploadStatistics/environet:Messages/environet:Message') as $message) {
									$type = (string) $message->attributes('environet')['type'];
									$code = isset($message->attributes('environet')['code']) ? (int) $message->attributes()['code'] : null;
									$console->writeLine(ucfirst($type) . ": " . ($code ? "[$code], " : "") . $message->__toString());
								}
							} catch (\Exception $e) {
								$console->writeLine('Failed to parse response XML', null, null, true);
							}

							$successful ++;
						} catch (\Exception $e) {
							$filename = $payloadStorage . '/' . date('YmdHis') . '_' . $xmlMPointId . '.xml';
							file_put_contents($filename, $xmlPayload->asXML());

							$console->writeLine('failed');
							$console->writeLine(sprintf("Upload for station NCD %s failed, response: ", $xmlMPointId), null, null, true);
							$console->writeLine($e->getMessage(), null, null, true);
							$console->writeLine('Payload stored: ' . ltrim(str_replace(SRC_PATH, '', $filename), '/'), null, null, true);
							$console->writeLine('---', null, null, true);
							$failed ++;
							$resourceSuccess = false;
						}
					}


					if ($resourceSuccess) {
						//Move the file to processed directory if it was successfully uploaded
						if ($resource->getLocalCopyPath()) {
							$dir = dirname($resource->getLocalCopyPath());
							$filename = basename($resource->getLocalCopyPath());
							$processedDir = $dir . '/processed';
							if (!is_dir($processedDir)) {
								mkdir($processedDir, 0755, true);
							}
							if (file_exists($dir . '/' . $filename)) {
								rename($dir . '/' . $filename, $processedDir . '/' . $filename);
							}
						}
					}
				} catch (\Exception $e) {
					$console->writeLine(
						sprintf(
							"Parsing of %s (first 100 characters: \"%s\") failed, response: %s",
							$resource->getName(),
							$this->previewString($resource->getContents(), 100),
							$e->getMessage()
						),
						null,
						null,
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
			$console->writeLine("Successful downloads from data provider: $successfulDownloads");
			$console->writeLine("Successful uploads to distribution node: $successful");
			$console->writeLine("Failed downloads from data provider: $failedDownloads", $thereWasAnError, $thereWasAnError);
			$console->writeLine("Failed uploads to distribution node: $failed", $thereWasAnError, $thereWasAnError);
			$console->writeLine("Monitoring points missing in data: " . $missingMonitoringPoints, $thereWasAnError, $thereWasAnError);
			if (count($allNCDs) > 0) {
				$console->writeLine("Following monitoring points missing in data: " . implode(" ", $allNCDs), null, null, true);
			}
		}
		$console->writeLine('Running plugin finished -------------------------------------------------------------------------------------', null, null, true, true);

		$console->resetDatePrefix();
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
