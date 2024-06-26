<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\General\HttpClient\Exceptions\HttpClientException;
use Environet\Sys\General\HttpClient\HttpClient;
use Environet\Sys\General\HttpClient\Request;
use Environet\Sys\Plugins\ApiClient;
use Environet\Sys\Plugins\PluginBuilder;
use Environet\Sys\Plugins\Resource;
use Environet\Sys\Plugins\WithConversionsConfigTrait;
use Exception;
use ZipArchive;

/**
 * Class HttpTransport
 *
 * Transport layer for http connections.
 *
 * @package Environet\Sys\Plugins\Transports
 * @author  SRG Group <dev@srg.hu>
 */
class HttpTransport extends AbstractTransport {

	use WithConversionsConfigTrait;

	const TYPE_MANUAL = 1;
	const TYPE_JSON   = 2;

	/**
	 * @var array
	 */
	protected $pluginConfig;

	/**
	 * @var string URL of the data source
	 */
	private $url;

	/**
	 * @var int 1 if the url is an index of files
	 */
	private $isIndex;

	/**
	 * @var string Regex pattern if the url is an index page
	 */
	private $indexRegexPattern;

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
	 * HttpTransport constructor.
	 *
	 * @param array $config
	 * @param array $pluginConfig
	 */
	public function __construct(array $config, array $pluginConfig = []) {
		$this->url = $config['url'];
		$this->isIndex = $config['isIndex'] ?? null;
		$this->indexRegexPattern = $config['indexRegexPattern'] ?? null;
		$this->conversionsFilename = $config['conversionsFilename'] ?? null;
		$this->username = $config['username'] ?? null;
		$this->password = $config['password'] ?? null;
		$this->pluginConfig = $pluginConfig;
		parent::__construct($config, $pluginConfig);
	}


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console, PluginBuilder $builder): HttpTransport {
		$console->writeLine('');
		$console->writeLine("Configuring http transport", Console::COLOR_YELLOW);

		$monitoringPointType = self::createMonitoringPointTypeConfig($console);

		while (true) {
			//Ask for type of url processing
			$console->writeLine("Do you want to enter the URL manually, or it should be built based on a JSON configuration file?");
			$type = (int) $console->askWithDefault("" . self::TYPE_MANUAL . " - manual, " . self::TYPE_JSON . " - JSON config", 1);
			if (in_array($type, [self::TYPE_MANUAL, self::TYPE_JSON])) {
				break;
			}
		}

		if ($type == self::TYPE_MANUAL) {
			//Manual URL type
			$url = $console->ask("Enter the url of data to be imported e.g.: https://example.com/data.txt");

			$isIndex = $console->askWithDefault('Is the url is the list (index) of multiple files? [y/N]', 'n');
			$isIndex = trim(strtolower($isIndex)) === 'y';

			$indexRegexPattern = '';
			if ($isIndex) {
				//Get regex pattern finding files
				$indexRegexPattern = $console->ask("Please enter the regex patter for the relative path of files, with '/' as delimiter, and with named match with name 'relativePath'. Example: /<a href=\"(?P<relativePath>[^\"]+\.csv)\"/i");
			}
		} elseif ($type == self::TYPE_JSON) {
			//Conversion JSON
			$conversionsFilename = $console->ask('Filename of conversion specifications:');
			$username = $console->askWithDefault('Username to access Web-API, if needed:', '');
			$password = $console->askWithDefault('Password to access Web-API, if needed:', '');
		}


		$config = [
			'url'                 => $url ?? null,
			'isIndex'             => isset($isIndex) ? ($isIndex ? 1 : 0) : null,
			'indexRegexPattern'   => $indexRegexPattern ?? null,
			'conversionsFilename' => $conversionsFilename ?? null,
			'username'            => $username ?? null,
			'password'            => $password ?? null,
			'monitoringPointType' => $monitoringPointType ?: null,
		];

		return new self($config);
	}


	/**
	 * @inheritDoc
	 */
	public function serializeConfiguration(): string {
		return 'url = "' . $this->url . '"' . "\n"
			. 'isIndex = ' . $this->isIndex . '' . "\n"
			. 'indexRegexPattern = "' . addcslashes($this->indexRegexPattern, '"') . '"' . "\n"
			. 'monitoringPointType = "' . $this->monitoringPointType . '"' . "\n";
	}


	/**
	 * @inheritDoc
	 * @throws HttpClientException
	 * @throws Exception
	 */
	public function get(Console $console, string $configFile): array {
		$resources = [];
		if ($this->conversionsFilename) {
			//Not manual url-type, but url processing based on convertions JSON file
			$resources = $this->buildConversionsUrls();
		} elseif ($this->isIndex) {
			//Fetch files based on HTML source
			$indexPageContents = (new HttpClient())->sendRequest(new Request($this->url))->getBody();
			$hasMatches = preg_match_all($this->indexRegexPattern, $indexPageContents, $matches);
			if ($hasMatches && !empty($matches['relativePath'])) {
				$urls = array_map(function ($match) {
					return (new Resource())->setUrl(rtrim($this->url, '/') . '/' . ltrim($match, '/'));
				}, $matches['relativePath']);
			}
		} else {
			//User url as file
			$resources = [(new Resource())->setUrl($this->url)];
		}

		foreach ($resources as $resource) {
			if (!empty($resource->getSubFile())) {
				//Url is a zip file
				$this->getFromZipFile($console, $resource);
			} else {
				//Url is a simple manual url
				$resource->setName(basename($resource->getUrl())); //Filename
				$resource->setContents((new HttpClient())->sendRequest(new Request($resource->getUrl()))->getBody());
				$console->writeLine('File downloaded from url: ' . $resource->getUrl() . ' (' . strlen($resource->getContents()) . ' bytes)');
			}
		}

		return $resources;
	}


	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return 'http transport';
	}


	/**
	 * @inheritDoc
	 */
	public static function getHelp(): string {
		return 'For acquiring measurement data via an http request.';
	}


	/**
	 * Get a zip file, and find files based on sub-file pattern in zip file's contents
	 *
	 * @param Console  $console
	 * @param Resource $resource
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function getFromZipFile(Console $console, Resource $resource): void {
		$zipUrl = $resource->getUrl();
		if (empty(($subFile = $resource->getSubFile()))) {
			//Subfile config is required for zip files
			throw new Exception('SubFile specification is required for zips');
		}

		//Get zip to a temp file
		$ext = pathinfo($zipUrl, PATHINFO_EXTENSION);
		$temp = tempnam(sys_get_temp_dir(), $ext);
		copy($zipUrl, $temp);
		$console->writeLine('Zip file downloaded from url: ' . $zipUrl);

		//Make resource
		$resource->setName(basename($zipUrl));

		$zip = new ZipArchive;
		$zip->open($temp);
		//Iterate over files in zip, and find the matching one.
		for ($i = 0; $i < $zip->numFiles; ++ $i) {
			$name = $zip->getNameIndex($i);
			if (fnmatch($subFile, $name)) {
				$resource->setContents($zip->getFromName($name));
				break;
			}
		}
		$zip->close();
		unlink($temp); //Delete temp file
	}


	/**
	 * Get an api client if given plugin config contains all required informations
	 *
	 * @return ApiClient
	 * @throws Exception
	 */
	protected function getApiClient(): ApiClient {
		if (!empty($this->pluginConfig['apiClient'])) {
			return new ApiClient($this->pluginConfig['apiClient']);
		}
		throw new Exception('Api client is required for this transport');
	}


	/**
	 * Build array of urls based on conversions JSON file
	 *
	 * @return array|Resource[]
	 * @throws Exception
	 */
	protected function buildConversionsUrls(): array {
		//Build some variables based on conversions config
		$conversions = $this->getConversionsConfig();
		$baseUrl = $this->url ?? null;
		$monitoringPointConversions = $conversions["monitoringPointConversions"] ?? [];
		$observedPropertyConversions = $conversions["observedPropertyConversions"] ?? [];

		//Query distribution node to get list of monitoring points and observed properties
		$sMonitoringPoints = $this->getApiClient()->requestMonitoringPoints();

		//Build data of monitoring points
		$allObservedProperties = [];
		$allNCDs = [];
		$monitoringPoints = [];
		foreach ($sMonitoringPoints as $type => $items) {
			if ($this->monitoringPointType && $this->monitoringPointType !== $type) {
				//Skip if conversions configuration is only for a single monitoring point type, and $type is not the same
				continue;
			}
			$postfix = $type === 'meteo' ? 'pst' : 'wgst';
			foreach ($items as $item) {
				//Add items with some extra informations
				$item['NCD'] = $item["ncd_$postfix"];
				$item['EUCD'] = $item["eucd_$postfix"];
				array_push($monitoringPoints, $item);

				//Collection all ncds, and properties
				array_push($allNCDs, $item["NCD"]);
				$allObservedProperties = array_merge($allObservedProperties, $item["observed_properties"] ?? []);
			}
		}
		$allObservedProperties = array_unique($allObservedProperties);

		// check if a monitoring point conversion variable is in URL pattern. If false, all monitoring points will be in one file
		$allMonitoringPointsInOneFile = !$monitoringPointConversions || !preg_match('/\[(' . implode('|', array_keys($monitoringPointConversions)) . ')\]/i', $baseUrl);

		// check if a observed property conversion variable is in URL pattern. If false, all observed property will be in one file
		$observedPropertyConversionsVariables = array_keys(array_merge(...array_values($observedPropertyConversions)));
		$allObservedPropertyInOneFile = !$observedPropertyConversions || !preg_match('/\[(' . implode('|', $observedPropertyConversionsVariables) . ')\]/i', $baseUrl);

		$resources = [];

		// Loop over list of monitoring points and observables
		foreach ($monitoringPoints as $monitoringPoint) {
			foreach ($monitoringPoint["observed_properties"] as $observedProperty) {
				// variable preparation
				$variables = $this->prepareVariables(
					$monitoringPoint["NCD"],
					$observedProperty,
					$monitoringPointConversions,
					$observedPropertyConversions
				);
				if ($variables === null) {
					//Variable preparation is not possible, skip this property
					continue;
				}

				// do variable substitution
				$url = $baseUrl;
				foreach ($variables as $key => $value) {
					$url = str_replace('[' . $key . ']', $value, $url);
				}

				// remove part after pipe symbol
				$urlParts = explode('|', $url);

				$resource = new Resource();
				if (count($urlParts) > 1) {
					$resource->setUrl($urlParts[0])->setSubFile($urlParts[1]);
				} else {
					$resource->setUrl($url);
				}

				if ($allMonitoringPointsInOneFile) {
					$resource->setPointNCDs($allNCDs);
				} else {
					$resource->setSpecificPointNCD($monitoringPoint['NCD']);
				}

				if ($allObservedPropertyInOneFile) {
					$resource->setPropertySymbols($allObservedProperties);
				} else {
					$resource->setSpecificPropertySymbol($observedProperty);
				}

				$resource->setObservedPropertyConversions($observedPropertyConversions);

				$resources[] = $resource;
				if ($allObservedPropertyInOneFile) {
					break;
				}
			}
			if ($allMonitoringPointsInOneFile) {
				break;
			}
		}

		return $resources;
	}


	/**
	 * Return list of variables from definitions for a certain monitoring point and a certain observed property
	 *
	 * @param string $ncd
	 * @param string $observedProperty            internal name of observed property for which variable preparation should be done
	 *
	 * @param array  $monitoringPointConversions
	 * @param array  $observedPropertyConversions
	 *
	 * @return array|null Null if variable preparation is not possible, otherwise an array of variables
	 */
	protected function prepareVariables(string $ncd, string $observedProperty, array $monitoringPointConversions, array $observedPropertyConversions): ?array {
		// variable preparation
		$variables = [
			"USERNAME" => $this->username,    // predefined property
			"PASSWORD" => $this->password    // predefined property
		];

		foreach ($monitoringPointConversions as $key => $value) {
			$variables += [$key => $ncd];
		}

		if (!empty($observedPropertyConversions)) {
			if (empty($observedPropertyConversions[$observedProperty])) {
				//Observed property is not in the conversion list, not mapped, this property should be skipped
				return null;
			}
			$variables += $observedPropertyConversions[$observedProperty];
		}


		return $variables;
	}


}
