<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\General\HttpClient\Exceptions\HttpClientException;
use Environet\Sys\General\HttpClient\HttpClient;
use Environet\Sys\General\HttpClient\Request;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\TransportInterface;

/**
 * Class HttpTransportExtended
 *
 * Transport layer for http connections. Generalizes URL to also contain variables in square brackets,
 * and files in a zip file separated by a pipe symbol. It also supports wildcards * and ? for file names in
 * zip files.
 *
 * @package Environet\Sys\Plugins\Transports
 * @author  SRG Group <dev@srg.hu>, <meyer@stasa.de>
 */
class HttpTransportExtended implements TransportInterface, BuilderLayerInterface {

	/**
	 * @var string URL of the data source
	 */
	private $url;

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
	 */
	public function __construct(array $config) {
		$this->url = $config['url'];

		// TODO: Make this configurable
		$this->monitoringPointConversions = [
			"MPID1" => "#####",
			"MPID2" => "#",
		];

		$this->observedPropertyConversions = [
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
	}


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console): HttpTransportExtended {
		$console->writeLine('');
		$console->writeLine("Configuring extended http transport", Console::COLOR_YELLOW);
		$url = $console->ask("Enter the url of data to be imported e.g.: https://example.com/data_[VARIABLE1].zip|file_in_zip_[VARIABLE2].txt");

		// TODO: Extend configuration: Add excel file for assignments, add excel file for formats
		// TODO: Parse files and store in $config

		$config = [
			'url' => $url,
			'monitoringPointConversions' => $monitoringPointConversions,
			'observedPropertyConversions' => $observedPropertyConversions,
		];

		return new self($config);
	}


	/**
	 * @inheritDoc
	 */
	public function serializeConfiguration(): string {
		return 'url = "' . $this->url . '"' . "\n";
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
		// As API call not yet available, use hard coded list for DWD
		$monitoringPoints = [
			[
				"NCD" => "87",							// national code of monitoring point
				"end_time" => "20300101T00:00:00Z",		// time at which monitoring point goes out of order
				/* ... */								// rest of fields from database
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
					$body = (new HttpClient())->sendRequest(new Request($url))->getBody();
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
