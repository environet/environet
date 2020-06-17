<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\General\HttpClient\Exceptions\HttpClientException;
use Environet\Sys\General\HttpClient\HttpClient;
use Environet\Sys\General\HttpClient\Request;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\TransportInterface;
use Environet\Sys\Plugins\ApiClient;

/**
 * Class HttpTransportExtended
 *
 * Transport layer for http connections. Generalizes URL to also contain variables in square brackets,
 * and files in a zip file separated by a pipe symbol. It also supports wildcards * and ? for file names in
 * zip files.
 *
 * @package Environet\Sys\Plugins\Transports
 * @author  SRG Group <dev@srg.hu>, STASA <info@stasa.de>
 */
class HttpTransportExtended implements TransportInterface, BuilderLayerInterface {

	/**
	 * @var string URL of the data source
	 */
	private $url;

	/**
	 * @var string Mode of operation as workaround while initialization file is not implemented: "DWD" or "LfU"
	 */
	private $mode;

	/**
	 * @var ApiClient Class for calling REST API
	 */
	private $apiClient;

	/**
	 * @var list of monitoring point's conversions to variables
	 */
	private $monitoringPointConversions;

	/**
	 * @var list of observed properties' conversions to variables
	 */
	private $observedPropertyConversions;

	/**
	 * HttpTransportExtended constructor.
	 *
	 * @param array $config
	 * @param array $pluginConfig
	 */
	public function __construct(array $config, array $pluginConfig = []) {
		$this->url = $config['url'];
		$this->mode = $config['mode'];
		if (sizeof($pluginConfig)>0) {
			$this->apiClient = new ApiClient($pluginConfig['apiClient']);
		} else {
			$this->apiClient = NULL;
		}

		// TODO: Make this configurable
		$monitoringPointConversionsDWD = [
			"MPID1" => "#####",
			"MPID2" => "#",
		];

		$observedPropertyConversionsDWD = [
			// precipitation summed over an hour [°C]
			"P_total_hourly" => [
				"OBS1" => "RR",
				"OBS2" => "precipitation",
				"OBS3" => "rr",
				"INT1" => "hourly",
				"INT2" => "stunde",
				"INT3" => "stundenwerte",
			],
			// current air temperature [°C]
			"ta" => [
				"OBS1" => "TU",
				"OBS2" => "air_temperature",
				"OBS3" => "tu",
				"INT1" => "hourly",
				"INT2" => "stunde",
				"INT3" => "stundenwerte",
			],
		];

		$monitoringPointConversionsLfU = [
			"MPID" => "#",
		];

		$observedPropertyConversionsLfU = [
			// current water level [cm]
			"h" => [
				"OBS" => "W",
			],
			// current river discharge [m³/s]
			"Q" => [
				"OBS" => "Q",
			],
			// current water temperature [°C]
			"tw" => [
				"OBS" => "WT",
			],
		];

		if ($this->mode == "DWD") {
			$this->monitoringPointConversions = $monitoringPointConversionsDWD;
			$this->observedPropertyConversions = $observedPropertyConversionsDWD;
		}
		else {
			$this->monitoringPointConversions = $monitoringPointConversionsLfU;
			$this->observedPropertyConversions = $observedPropertyConversionsLfU;
		}
	}


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console): HttpTransportExtended {
		$console->writeLine('');
		$console->writeLine("Configuring extended http transport", Console::COLOR_YELLOW);
		$url = $console->ask("Enter the url of data to be imported e.g.: https://example.com/data_[VARIABLE1].zip|file_in_zip_[VARIABLE2].txt", 200);
		$mode = $console->ask("Enter mode of operation [preliminary] (DWD/LfU)", 10);

		// TODO: Extend configuration: Add excel file for assignments, add excel file for formats
		// TODO: Parse files and store in $config

		$config = [
			'url' => $url,
			'mode' => $mode,
			'monitoringPointConversions' => $monitoringPointConversions,
			'observedPropertyConversions' => $observedPropertyConversions,
		];

		return new self($config);
	}


	/**
	 * @inheritDoc
	 */
	public function serializeConfiguration(): string {
		return 'url = "' . $this->url . '"' . "\n"
			. 'mode = "' . $this->mode . '"' . "\n";
	}


	/**
	 * Return list of variables from definitions for a certain monitoring point and a certain observed property
	 *
	 * @param string $NCD national code of monitoring point for which variable preparation should be done
	 * @param string $observedProperty internal name of observed property for which variable preparation should be done
	 * @return array
	 */
	public function prepareVariables(string $NCD, string $observedProperty) : array {
		// variable preparation
		$variables = [];

		foreach ($this->monitoringPointConversions as $key => $value) {
			preg_match_all("/#+/", $value, $matches, PREG_OFFSET_CAPTURE);
			if (count($matches) <= 0) {
				continue;
			}
			$s = $value;
			foreach ($matches[0] as $match) {
				if (strlen($match[0]) > 1) {
					$nr = str_pad($NCD, strlen($match[0]), "0", STR_PAD_LEFT);
				} else {
					$nr = $NCD;
				}
				$s = substr_replace($s, $nr, $match[1], strlen($match[0]));
			}
			$variables += [ $key => $s ];
		}

		$observedPropertyConversion = $this->observedPropertyConversions[$observedProperty];
		$variables += $observedPropertyConversion;

		return $variables;
	}


	/**
	 * @inheritDoc
	 * @throws HttpClientException
	 */
	public function get(): array {

		// Query distribution node to get list of monitoring points and observed properties
		$sMonitoringPoints = $this->apiClient->requestMonitoringPoints();
		//var_dump($sMonitoringPoints);

		$monitoringPoints = [];
		foreach ($sMonitoringPoints['hydro'] as $item) {
			$item["NCD"] = $item["ncd_wgst"];
			array_push($monitoringPoints, $item);
		}
		foreach ($sMonitoringPoints['meteo'] as $item) {
			$item["NCD"] = $item["ncd_pst"];
			array_push($monitoringPoints, $item);
		}

		//var_dump($monitoringPoints);
		/*
		// As API call not yet available, use hard coded list for DWD Germany
		$monitoringPointsDWD = [
			[
				"NCD" => "87",							// national code of monitoring point
				"end_time" => "20300101T00:00:00Z",		// time at which monitoring point goes out of order
				"observed_properties" => [				// list of observed properties measured at this monitoring point from table monitoring_point_observed_property
					"P_total_hourly",					// precipitation summed over an hour [mm]
				],
			],
			[
				"NCD" => "91",							// national code of monitoring point
				"end_time" => "20300101T00:00:00Z",		// time at which monitoring point goes out of order
				"observed_properties" => [				// list of observed properties measured at this monitoring point from table monitoring_point_observed_property
					"P_total_hourly",					// precipitation summed over an hour [mm]
					"ta",								// current air temperature [°C]
				],
			],
		];

		// As API call not yet available, use hard coded list for LfU Germany
		$monitoringPointsLfU = [
			[
				"NCD" => "10026301",					// national code of monitoring point
				"end_time" => "20300101T00:00:00Z",		// time at which monitoring point goes out of order
				"observed_properties" => [				// list of observed properties measured at this monitoring point from table monitoring_point_observed_property
					"h",								// current water level [cm]
					"Q",								// current river discharge [m³/s]
					"tw",								// current water temperature [°C]
				],
			],
			[
				"NCD" => "10032009",					// national code of monitoring point
				"end_time" => "20300101T00:00:00Z",		// time at which monitoring point goes out of order
				"observed_properties" => [				// list of observed properties measured at this monitoring point from table monitoring_point_observed_property
					"h",								// current water level [cm]
					"Q",								// current river discharge [m³/s]
				],
			],
		];

		if ($this->mode == "DWD") {
			$monitoringPoints = $monitoringPointsDWD;
		} else {
			$monitoringPoints = $monitoringPointsLfU;
		}

		*/

		$result = [];

		// Loop over list of monitoring points and observables
		foreach ($monitoringPoints as $monitoringPoint) {
			foreach ($monitoringPoint["observed_properties"] as $observedProperty) {
				// variable preparation
				$variables = $this->prepareVariables($monitoringPoint["NCD"], $observedProperty);

				// do variable substitution
				$url = $this->url;
				foreach ($variables as $key => $value) {
					$url = str_replace('[' . $key . ']', $value, $url);
				}

				// remove part after pipe symbol
				$parts = explode('|', $url);
				$url = $parts[0];

				echo "GET " . $url . PHP_EOL;

				if (count($parts) > 1) {
					// contains pipe symbol, decode zip
					$ext = pathinfo($url, PATHINFO_EXTENSION);
					$temp = tempnam(sys_get_temp_dir(), $ext);
					
					copy($url, $temp);
					
					$zip = new \ZipArchive;
					$zip->open($temp);
					$found = false;
					$data = '';
					for ($i = 0; $i < $zip->numFiles; ++$i) {
						$name = $zip->getNameIndex($i);
						if (fnmatch($parts[1], $name)) {
							$data = $zip->getFromName($name);
							$found = true;
							break;
						}
					}
					$zip->close();
					$body = new \stdClass();
					$body->contents = $data;
					unlink($temp);
				} else {
					$body = new \stdClass();
					$body->contents = (new HttpClient())->sendRequest(new Request($url))->getBody();
				}
				array_push($result, $body);
			}
		}

		return $result;
	}


	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return 'http transport with extended url syntax';
	}


	/**
	 * @inheritDoc
	 */
	public static function getHelp(): string {
		return 'For acquiring measurement data via an http request using url syntax extension.';
	}


}
