<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\General\HttpClient\Exceptions\HttpClientException;
use Environet\Sys\General\HttpClient\HttpClient;
use Environet\Sys\General\HttpClient\Request;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\TransportInterface;
use Environet\Sys\Plugins\ApiClient;
use Environet\Sys\Plugins\Resource;
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
	 * @var string Filename for JSON file with conversions of variables
	 */
	private $conversionsFilename;

	/**
	 * @var string username for Web-API
	 */
	private $username;

	/**
	 * @var string password for Web-API
	 */
	private $password;

	/**
	 * @var ApiClient Class for calling REST API
	 */
	private $apiClient;

	/**
	 * @var string URL of the data source
	 */
	private $url;

	/**
	 * @var string Which stations to query: "hydro" or "meteo". Query both, if not one of these or if not specified
	 */
	private $monitoringPointType;

	/**
	 * @var array List of monitoring point conversions
	 */
	private $monitoringPointConversions;

	/**
	 * @var array List of observed property conversions
	 */
	private $observedPropertyConversions;


	/**
	 * HttpTransportExtended constructor.
	 *
	 * @param array $config
	 * @param array $pluginConfig
	 */
	public function __construct(array $config, array $pluginConfig = []) {
		$this->conversionsFilename = $config['conversionsFilename'];

		$configurationsPath = SRC_PATH . '/conf/plugins/configurations/';
		$conversions = file_get_contents($configurationsPath . $this->conversionsFilename);
		$conversions = JSON_decode($conversions, true);
		$this->url = $conversions["generalInformation"]["URLPattern"];
		$this->monitoringPointConversions = $conversions["monitoringPointConversions"];
		$this->observedPropertyConversions = $conversions["observedPropertyConversions"];

		$this->username = $config['username'];
		$this->password = $config['password'];

		if (array_key_exists('monitoringPointType', $config)) {
			$this->monitoringPointType = $config['monitoringPointType'];
		} else {
			$this->monitoringPointType = 'both';
		}

		if (sizeof($pluginConfig)>0) {
			$this->apiClient = new ApiClient($pluginConfig['apiClient']);
		} else {
			$this->apiClient = NULL;
		}
	}


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console): HttpTransportExtended {
		$console->writeLine('');
		$console->writeLine("Configuring extended http transport", Console::COLOR_YELLOW);
		$conversionsFilename = $console->ask("Filename of conversion specifications", 2000);
		$username = $console->ask("Username to access Web-API, if needed", 64);
		$password = $console->ask("Password to access Web-API, if needed", 64);
		$monitoringPointType = $console->ask("Type of monitoring points to query: 'hydro', 'meteo' or else both", 64);

		$config = [
			'conversionsFilename' => $conversionsFilename,
			'username' => $username,
			'password' => $password,
			'monitoringPointType' => $monitoringPointType
		];

		return new self($config);
	}


	/**
	 * @inheritDoc
	 */
	public function serializeConfiguration(): string {
		return 'conversionsFilename = "' . $this->conversionsFilename . '"' . "\n"
			. 'username = "' . $this->username . '"' . "\n"
			. 'password = "' . $this->password . '"' . "\n"
			. 'monitoringPointType = "' . $this->monitoringPointType . '"' . "\n";
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
		$variables = [
			"USERNAME" => $this->username,	// predefined property
			"PASSWORD" => $this->password 	// predefined property
		];

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

		$observedPropertyConversions = $this->observedPropertyConversions[$observedProperty];
		if ($observedPropertyConversions) {
			$variables += $observedPropertyConversions;
		}

		return $variables;
	}


	/**
	 * @inheritDoc
	 * @throws HttpClientException
	 */
	public function get(): array {

		// Query distribution node to get list of monitoring points and observed properties
		$sMonitoringPoints = $this->apiClient->requestMonitoringPoints();

		//ini_set('xdebug.var_display_max_depth', '10');
		//ini_set('xdebug.var_display_max_children', '256');
		//ini_set('xdebug.var_display_max_data', '1024');
		//var_dump($sMonitoringPoints);

		$allObservedProperties = [];
		$allNCDs = [];
		$monitoringPoints = [];
		if ($this->monitoringPointType !== 'meteo') {
			foreach ($sMonitoringPoints['hydro'] as $item) {
				$item["NCD"] = $item["ncd_wgst"];
				$item["EUCD"] = $item["eucd_wgst"];
				array_push($monitoringPoints, $item);
				array_push($allNCDs, $item["NCD"]);
				$allObservedProperties = array_merge($item["observed_properties"]);
			}
		}
		if ($this->monitoringPointType !== 'hydro') {
			foreach ($sMonitoringPoints['meteo'] as $item) {
				$item["NCD"] = $item["ncd_pst"];
				$item["EUCD"] = $item["eucd_pst"];
				array_push($monitoringPoints, $item);
				array_push($allNCDs, $item["NCD"]);
				$allObservedProperties = array_merge($item["observed_properties"]);
			}
		}
		$allObservedProperties = array_unique($allObservedProperties);

		// check if a monitoring point conversion variable is in URL pattern
		$allMonitoringPointsInOneFile = true;
		foreach ($this->monitoringPointConversions as $key => $value) {
			if (strpos($this->url, "[".$key."]") !== false) {
				$allMonitoringPointsInOneFile = false;
				break;
			}
		}

		// check if a observed property conversion variable is in URL pattern
		$allObservedPropertyInOneFile = true;
		foreach ($this->observedPropertyConversions as $property) {
			foreach ($property as $key => $value) {
				if (strpos($this->url, "[".$key."]") !== false) {
					$allObservedPropertyInOneFile = false;
					break;
				}
			}
		}

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

				$resource = new Resource();
				$resource->name = $url;

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
					$resource->contents = $data;
					unlink($temp);
				} else {
					$resource->contents = (new HttpClient())->sendRequest(new Request($url))->getBody();
				}
				$resource->meta = [
					"MonitoringPointNCDs" => $allMonitoringPointsInOneFile ? $allNCDs : [ $monitoringPoint["NCD"] ], 
					"ObservedPropertySymbols" => $allObservedPropertyInOneFile ? $allObservedProperties : [ $observedProperty ],
					"observedPropertyConversions" => $this->observedPropertyConversions,
				];
				array_push($result, $resource);
				if ($allObservedPropertyInOneFile) {
					break;
				}
			}
			if ($allMonitoringPointsInOneFile) {
				break;
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
