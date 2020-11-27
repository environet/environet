<?php

namespace Environet\Sys\Plugins\Parsers;

use DateTime;
use DateTimeZone;
use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\ParserInterface;
use Environet\Sys\Xml\CreateInputXml;
use Environet\Sys\Xml\Exceptions\CreateInputXmlException;
use Environet\Sys\Xml\Model\InputXmlData;
use Environet\Sys\Xml\Model\InputXmlPropertyData;
use Environet\Sys\Plugins\Resource;
use Exception;
use SimpleXMLElement;

/**
 * Class XMLParser
 *
 * Parser layer for XML files
 *
 * @package Environet\Sys\Plugins\Parsers
 * @author  SRG Group <dev@srg.hu>, STASA <info@stasa.de>
 */
class XmlParser extends AbstractParser implements BuilderLayerInterface {

	const API_TIME_FORMAT_STRING = 'Y-m-d\TH:i:sP';

	/**
	 * @var string Separator to group thousands in values. May be ""
	 */
	private $separatorThousands;

	/**
	 * @var bool if true parser will process empty tags as missing value
	 */
	private $skipEmptyValueTag;

	/**
	 * @var string Separator for decimals.
	 */
	private $separatorDecimals;

	/**
	 * @var string Filename of JSON file which contains formats for xml
	 */
	private $formatsFilename;

	/**
	 * @var array Format specifications, where to find which information in xml file
	 */
	private $formats;


	/**
	 * XMLParser constructor.
	 *
	 * @param array $config
	 *
	 * @throws Exception
	 */
	public function __construct(array $config) {

		$this->separatorThousands = $config['separatorThousands'];
		$this->separatorDecimals = $config['separatorDecimals'];
		$this->formatsFilename = $config['formatsFilename'];
		$this->skipEmptyValueTag = isset($config['skipEmptyValueTag']) ? (bool) $config['skipEmptyValueTag'] : false;

		parent::__construct($config);
	}


	/**
	 * Recursive function to parse a xml tree to acquire values for given parameters from xml tree
	 *
	 * @param SimpleXMLElement $xml              xml element to parse
	 * @param array            $formats          format of information to be gathered from xml, including tag hierarchies for different parameters
	 * @param array            $resolved         table of information found. 1st index is the entry if there are multiple, 2nd index is information, call with "[]"
	 * @param int              $hierarchyCounter level of hierarchy, call with "0"
	 *
	 * @return array list of information gathered from xml
	 * @throws Exception
	 */
	private function parseIntoHierarchy(SimpleXMLElement $xml, array $formats, array $resolved, int $hierarchyCounter): array {
		if ($hierarchyCounter > 10) {
			throw new Exception("XML hierarchy deeper than 10");
		}

		if (!count($formats)) {
			echo "Error condition 1: Call, but all information already resolved.";

			return [];
		}

		// get groups of common hierarchy
		$commonElements = [];
		while (($common = $this->getAndStripOneCommonElement($formats))) {
			array_push($commonElements, $common);
		}
		$xpathCommonElements = implode('/', $commonElements);

		// Finish condition 1: No common elements, but unresolved information
		if ($xpathCommonElements == "") {
			throw new Exception("Unresolved information");
		}

		// get groups
		$flatList = [];
		$groups = $xml->xpath($xpathCommonElements);

		if ($groups == null) {
			throw new Exception("Given elements do not exist in file: " . $xpathCommonElements);
		}

		foreach ($groups as $group) {
			// count elements and resolve those which are unique
			$nResolved = 0;
			$groupResolved = $resolved;
			$formatsNew = [];
			foreach ($formats as $format) {
				if (($xpath = implode('/', $format["Tag Hierarchy"]))) {
					// desired information is sub-item of group
					$subXml = $group->xpath($xpath);
				} else {
					// desired information is group itself
					$subXml = $group;
					if (!empty($format["Attribute"]) && $subXml->getName() === end($commonElements)) {
						// desired information is attribute of group-defining tag
						$item = [];
						$item["Type"] = $format["Parameter"] ?? null;
						$item["Value"] = $subXml[0][$format["Attribute"]]->__toString();
						$item["Format"] = $format["Value"] ?? null;
						$item["Unit"] = $format["Unit"] ?? null;
						array_push($groupResolved, $item);
						++ $nResolved;
					}
				}
				if ($subXml == null) {
					if ($format["optional"]) {
						continue;
					}
					throw new Exception("Given elements do not exist in file: " . $xpath);
				}
				if (count($subXml) === 1) {
					$subXml = $subXml[0];
					$item = [];
					$item["Type"] = $format["Parameter"];
					if (empty($format["Attribute"])) {
						$item["Value"] = $subXml->__toString();
					} else {
						$item["Value"] = $subXml[$format["Attribute"]]->__toString();
					}

					if ($item['Type'] === 'ObservedPropertyValue' && $item['Value'] === '') {
						if ($this->skipEmptyValueTag) {
							//Skip empty values, jump to nex group
							continue 2;
						} else {
							//Convert empty values to 0
							$item['Value'] = '0';
						}
					}

					$item["Format"] = $format["Value"] ?? null;
					$item["Unit"] = $format["Unit"] ?? null;
					array_push($groupResolved, $item);
					++ $nResolved;
				} else {
					array_push($formatsNew, $format);
				}
			}

			if (count($formatsNew) > 1) {
				// do recursion
				$flatList = array_merge($flatList, $this->parseIntoHierarchy($group, $formatsNew, $groupResolved, $hierarchyCounter + 1));
			} else {
				// Finish condition 3: Success
				// all information available. Return flat list entry from resolved
				array_push($flatList, $groupResolved);
			}
		}// group

		return $flatList;
	}


	/**
	 * Get internal symbol for observed property from external symbol. Conversion between symbols is given by
	 * variable conversion information.
	 *
	 * @param array  $observedPropertyConversions
	 * @param string $variableName name of variable definition for observed property. E.g. "OBS"
	 * @param string $symbol       external symbol for observed property
	 *
	 * @return string internal symbol for observed property
	 */
	private function getInternalObservedPropertySymbol(array $observedPropertyConversions, string $variableName, string $symbol): string {
		foreach ($observedPropertyConversions as $key => $value) {
			if ($value[$variableName] && $value[$variableName] == $symbol) {
				return $key;
			}
		}

		return "";
	}


	/**
	 * Get item from list, for which parameter name has a certain value. List item is an associative array in which parameter names are keys.
	 *
	 * @param array  $list           list of items.
	 * @param string $parameterName  name of parameter
	 * @param string $parameterValue value of parameter
	 *
	 * @return array items found, "[]" if none found.
	 */
	private function getParameter(array $list, string $parameterName, string $parameterValue): array {
		$result = [];
		foreach ($list as $item) {
			if (array_key_exists($parameterName, $item) && $item[$parameterName] == $parameterValue) {
				$result = $item;
				break;
			}
		}

		return $result;
	}


	/**
	 * Deletes item from list for specified parameter and value. List item is an associative array in which parameter names are keys.
	 *
	 * @param array  $list           list to delete items from
	 * @param string $parameterName  name of parameter
	 * @param string $parameterValue value for parameter
	 */
	private function delete(array &$list, string $parameterName, string $parameterValue) {
		foreach ($list as $key => $item) {
			if (array_key_exists($parameterName, $item) && $item[$parameterName] == $parameterValue) {
				unset($list[$key]);
			}
		}
		$list = array_values($list);
	}


	/**
	 * Convert a given unit to base unit for observed property.
	 * Default units are:
	 * water level h: cm
	 * river discharge Q: m3/s
	 * water temperature tw: °C
	 * precipitation P: mm
	 * air temperature ta: °C
	 *
	 * @param float  $value  measured value to convert
	 * @param string $symbol internal symbol for observed property
	 * @param string $unit   unit in which $value is given
	 */
	private function convertUnitToBaseUnit(float &$value, string $symbol, string $unit) {
		$symbol = explode('_', $symbol)[0];
		$unit = strtolower($unit);

		//Convert value in case of some symbols and units
		//For Q (m³/s), tw (°C), and ta °C no conversions are necessary
		if ($symbol == 'h') {
			if ($unit == "mm") {
				$value /= 10;
			} elseif ($unit == "m") {
				$value *= 100;
			}
		} elseif ($symbol == "P") {
			if (in_array($unit, ['cm', 'cm/h'])) {
				$value *= 10;
			} elseif (in_array($unit, ['m', 'm/h'])) {
				$value *= 1000;
			}
		}
	}


	/**
	 * Returns one common element from xml tag hierarchy and strips it from format information which describes xml format.
	 *
	 * @param array $formats format information, including tag hierarchies for different parameters, which should be read from xml
	 *
	 * @return string The first common element of tag hierarchy, if any. If there is none, "" is returned.
	 * @throws Exception
	 */
	private function getAndStripOneCommonElement(array &$formats): ?string {
		if (empty($formats) || !isset($formats[0]['Tag Hierarchy']) || empty($formats[0]['Tag Hierarchy'])) {
			//Formats is empty, return empty string
			return null;
		}
		if (count($formats) === 1 && count($formats[0]['Tag Hierarchy'])) {
			//Formats only has one element, return this singe item
			return array_shift($formats[0]['Tag Hierarchy']);
		}

		$difference = false;
		$formatsCount = count($formats);
		for ($i = 1; $i < $formatsCount; ++ $i) {
			if (empty($formats[$i]['Tag Hierarchy'][0]) ||
			    empty($formats[$i - 1]['Tag Hierarchy'][0]) ||
			    $formats[$i]['Tag Hierarchy'][0] != $formats[$i - 1]['Tag Hierarchy'][0]
			) {
				$difference = true;
			}
		}
		if ($difference) {
			return null;
		}
		$result = $formats[0]["Tag Hierarchy"][0];
		for ($i = 0; $i < $formatsCount; ++ $i) {
			array_shift($formats[$i]["Tag Hierarchy"]);
		}

		return $result;
	}


	/**
	 * Assembles date from componentes like day, month, year, hour and minute
	 *
	 * @param array $entry list of parsed properties, in which separate items for day, month, etc... may occur. Separate items are joined to a
	 *                     "DateTime" item and deleted from $entry. "DateTime" has time format as given by API_TIME_FORMAT_STRING
	 *
	 * @throws Exception
	 */
	private function assembleDate(array &$entry) {
		$DateTime = $this->getParameter($entry, "Type", "DateTime");
		$Date = $this->getParameter($entry, "Type", "Date");
		$Time = $this->getParameter($entry, "Type", "Time");
		$Year = $this->getParameter($entry, "Type", "Year");
		$Month = $this->getParameter($entry, "Type", "Month");
		$Day = $this->getParameter($entry, "Type", "Day");
		$Hour = $this->getParameter($entry, "Type", "Hour");
		$Minute = $this->getParameter($entry, "Type", "Minute");

		$result = [
			"Type"   => "DateTime",
			"Value"  => "",
			"Format" => self::API_TIME_FORMAT_STRING,
			"Unit"   => null
		];

		if ($Year && $Month && $Day && $Hour && $Minute) {
			$t = mktime(
				strval($Hour["Value"]),
				strval($Minute["Value"]),
				0,
				strval($Month["Value"]),
				strval($Day["Value"]),
				strval($Year["Value"])
			);
			$format = "dmY H:i:s";
			$dateLocal = date($format, $t);
			$date = DateTime::createFromFormat($format, $dateLocal, $this->getTimeZone());
			$date->setTimezone(new DateTimeZone('UTC'));
			$result["Value"] = $date->format(self::API_TIME_FORMAT_STRING);
			$this->delete($entry, "Type", "Year");
			$this->delete($entry, "Type", "Month");
			$this->delete($entry, "Type", "Day");
			$this->delete($entry, "Type", "Hour");
			$this->delete($entry, "Type", "Minute");
		} elseif ($Date && $Time) {
			$date = DateTime::createFromFormat($Date["Format"] . ' ' . $Time["Format"], $Date["Value"] . ' ' . $Time["Value"], $this->getTimeZone());
			if (!$date) {
				echo("Warning: Invalid date or time format: " . $Date["Format"] . " - " . $Date["Value"]
				     . " -- " . $Time["Format"] . " - " . $Time["Value"] . ". Replaced with 1970-01-01\r\n");
				$date = new DateTime('1970-01-01T00:00:00Z');
			}
			$date->setTimezone(new DateTimeZone('UTC'));
			$result["Value"] = $date->format(self::API_TIME_FORMAT_STRING);
			$this->delete($entry, "Type", "Date");
			$this->delete($entry, "Type", "Time");
		} elseif ($DateTime) {
			$date = DateTime::createFromFormat($DateTime["Format"], $DateTime["Value"], $this->getTimeZone());
			if (!$date) {
				echo("Warning: Invalid datetime format: " . $DateTime["Format"] . " --- " . $DateTime["Value"] . ". Replaced with 1970-01-01\r\n");
				$date = new DateTime('1970-01-01T00:00:00Z');
			}
			$date->setTimezone(new DateTimeZone('UTC'));
			$result["Value"] = $date->format(self::API_TIME_FORMAT_STRING);
			$this->delete($entry, "Type", "DateTime");
		} else {
			throw new Exception("Incomplete date");
		}
		array_push($entry, $result);
	}


	/**
	 * Convert value parameters in entry: Remove thousands separator, change decimal separator to ".",
	 * add entry for unit if not available and convert values to
	 * base units.
	 *
	 * @param array $entry entry with parsed information
	 *
	 * @return bool returns true, if value is valid. Value may be empty string if not available. In this case value is invalid.
	 */
	private function convertValue(array &$entry): bool {
		$itemUnit = $this->getParameter($entry, "Type", "ObservedPropertyUnit");
		$itemSymbol = $this->getParameter($entry, "Type", "ObservedPropertySymbol");
		$valid = false;
		foreach ($entry as &$item) {
			if ($item["Type"] == "ObservedPropertyValue") {
				if ($item["Value"] != "") {
					$valid = true;
					if ($this->separatorThousands != "") {
						$item["Value"] = str_replace($this->separatorThousands, "", $item["Value"]);
					}
					if ($this->separatorDecimals != "." && $this->separatorDecimals != "") {
						$item["Value"] = str_replace($this->separatorDecimals, ".", $item["Value"]);
					}
					if (!$itemUnit) {
						$elem = [
							"Type"   => "ObservedPropertyUnit",
							"Value"  => $item["Unit"],
							"Format" => null,
							"Unit"   => null,
						];
						array_push($entry, $elem);
						$unit = $item["Unit"];
					} else {
						$unit = $itemUnit["Value"];
					}
					$this->convertUnitToBaseUnit($item["Value"], $itemSymbol["Value"], $unit);
				}
			}
		}

		return $valid;
	}


	/**
	 * Convert value parameters for whole list and deletes entries if value is not valid.
	 *
	 * @param array $flatList list of parsed information
	 *
	 * @see convertValue
	 */
	private function convertValues(array &$flatList) {
		foreach ($flatList as $key => &$entry) {
			if (!$this->convertValue($entry)) {
				unset($flatList[$key]);
			}
		}
		$flatList = array_values($flatList);
	}


	/**
	 * Create XML data from the monitoring point input array.
	 *
	 * @param array $mPointsArray
	 *
	 * @return SimpleXMLElement[]
	 * @throws CreateInputXmlException
	 * @see CreateInputXml
	 * @see InputXmlData
	 */
	private function meteringPointInputXmlsFromArray(array $mPointsArray): array {
		$payloads = [];

		$creator = new CreateInputXml();
		foreach ($mPointsArray as $mPointId => $properties) {
			$properties = array_map(function ($key, $value) {
				return new InputXmlPropertyData($key, $value);
			}, array_keys($properties), $properties);
			array_push($payloads, $creator->generateXml(new InputXmlData($mPointId, $properties)));
		}

		return $payloads;
	}


	/**
	 * @return array|false|mixed|string
	 * @throws Exception
	 */
	protected function getFormatsConfig(): array {
		if (is_null($this->formats)) {
			$formatsPathname = CONFIGURATION_PATH . '/' . $this->formatsFilename; //Path of file is in a fixed location
			if (!(file_exists($formatsPathname) && //File must be existing
			      ($formats = file_get_contents($formatsPathname)) && //File must be not-empty and readable
			      ($formats = json_decode($formats, true)) //Decode to json
			)) {
				throw new Exception("Syntax error in json string of formats configuration file '$formatsPathname', or file does not exist.");
			}
			$this->formats = $formats;
		}

		return $this->formats;
	}


	/**
	 * Assemble dates in whole list of entries.
	 *
	 * @param array $flatList list of parsed information. Entries are themselve lists of parsed parameters.
	 *
	 * @throws Exception
	 * @see assembleDate
	 */
	private function assembleDates(array &$flatList) {
		foreach ($flatList as &$entry) {
			$this->assembleDate($entry);
		}
	}


	/**
	 * @inheritDoc
	 * @throws CreateInputXmlException
	 * @throws Exception
	 */
	public function parse(Resource $resource): array {
		echo "Received " . strlen($resource->contents) . " characters.\r\n";

		$resource->contents = str_replace("xlink:href", "href", $resource->contents); // Workaround for WaterML 2.0

		libxml_use_internal_errors(true); // this turns off spitting parsing errors on screen
		$xml = new SimpleXMLElement($resource->contents);
		if (!empty($ns = $xml->getDocNamespaces())) {
			$xml->registerXPathNamespace('def', array_values($ns)[0]);
		}

		$formats = $this->getFormatsConfig();

		// strip top-level element from formats
		if (empty($this->getAndStripOneCommonElement($formats))) {
			throw new Exception("XML definition does not have a top-level element");
		}

		$flatList = $this->parseIntoHierarchy($xml, $formats, [], 0);

		// replace external observed property symbols and add missing information from API-Call (Monitoring Point or Observed Property Symbol)
		if ($resource->meta) {
			$newEntries = [];
			foreach ($flatList as $key => &$entry) {
				if (!$this->getParameter($entry, "Type", "MonitoringPoint") && !empty($resource->meta["MonitoringPointNCDs"])) {
					//Add monitoring point national code from API-Call
					$elem = [
						"Type"   => "MonitoringPoint",
						"Value"  => $resource->meta["MonitoringPointNCDs"][0],
						"Format" => null,
						"Unit"   => null,
					];
					array_push($entry, $elem);
				}

				$obs = $this->getParameter($entry, "Type", "ObservedPropertySymbol");
				if ($obs) {
					// convert external (in-file) symbol to internal symbol
					$symbolNameInFile = $obs["Value"] ?? null;
					$variableName = $obs["Format"] ?? null;
					if ($symbolNameInFile && $variableName) {
						if (($symbol = $this->getInternalObservedPropertySymbol($resource->meta["observedPropertyConversions"], $variableName, $symbolNameInFile))) {
							$this->delete($entry, "Type", "ObservedPropertySymbol");
							$obs["Value"] = $symbol;
							array_push($entry, $obs);
						} else {
							unset($flatList[$key]); // Delete whole entry as observed property was not found
						}
					}
				} else {
					if (isset($resource->meta["ObservedPropertySymbols"]) && count($resource->meta["ObservedPropertySymbols"]) === 1) {
						// Only one observed property by call: Add observed property symbol from API-Call
						$elem = [
							"Type"   => "ObservedPropertySymbol",
							"Value"  => $resource->meta["ObservedPropertySymbols"][0],
							"Format" => null,
							"Unit"   => null,
						];
						// delete all occurrences of ObservedPropertyValue with wrong symbol
						foreach ($entry as $ekey => &$e) {
							if ($e["Type"] == "ObservedPropertyValue" && $e["Format"] != $elem["Value"]) {
								unset($entry[$ekey]);
							}
						}
						$entry = array_values($entry);
						array_push($entry, $elem);
					} else {
						// add observed property symbol from ObservedPropertyValue
						$count = 0;
						foreach ($entry as &$e) {
							if ($e["Type"] == "ObservedPropertyValue") {
								// copy entry to new entries, because multiple occurrences of "ObservedPropertyValue" may be
								// present in $entry for different observed properties
								$newEntry = $entry;
								$prop = $e["Format"];
								$elem = [
									"Type"   => "ObservedPropertySymbol",
									"Value"  => $prop,
									"Format" => null,
									"Unit"   => null,
								];
								array_push($newEntry, $elem);
								// delete all occurrences of ObservedPropertyValue with wrong symbol
								foreach ($newEntry as $newenkey => $newenval) {
									if ($newenval["Type"] == "ObservedPropertyValue" && $newenval["Format"] != $prop) {
										unset($newEntry[$newenkey]);
									}
								}
								$newEntry = array_values($newEntry);
								array_push($newEntries, $newEntry);
								++ $count;
							}
						}
						if ($count == 0) {
							throw new Exception("No value for any observed property in entry.");
						}
						unset($flatList[$key]);
					}
				}
			}
			$flatList = array_values($flatList);
			$flatList = array_merge($flatList, $newEntries);
		}

		// delete entries which do not fit to API-call (extra monitoring points, extra observed properties)
		if ($resource->meta && (!isset($resource->meta["keepExtraData"]) || !$resource->meta["keepExtraData"])) {
			foreach ($flatList as $key => $entry) {
				$mp = $this->getParameter($entry, "Type", "MonitoringPoint");
				$obs = $this->getParameter($entry, "Type", "ObservedPropertySymbol");
				if (!in_array($mp["Value"], $resource->meta["MonitoringPointNCDs"]) || !in_array($obs["Value"], $resource->meta["ObservedPropertySymbols"])) {
					unset($flatList[$key]);
				}
			}
			$flatList = array_values($flatList);
		}

		$this->assembleDates($flatList);
		$this->convertValues($flatList);

		$resultArray = [];
		foreach ($flatList as $line) {
			$mp = $this->getParameter($line, "Type", "MonitoringPoint")["Value"];

			if (!array_key_exists($mp, $resultArray)) {
				$resultArray[$mp] = [];
			}

			$obs = $this->getParameter($line, "Type", "ObservedPropertySymbol")["Value"];

			if (!array_key_exists($obs, $resultArray[$mp])) {
				$resultArray[$mp][$obs] = [];
			}

			$time = $this->getParameter($line, "Type", "DateTime")["Value"];
			$value = strval($this->getParameter($line, "Type", "ObservedPropertyValue")["Value"]);

			$resultArray[$mp][$obs] = array_merge(
				$resultArray[$mp][$obs],
				[
					[
						'time'  => $time,
						'value' => $value
					]
				]
			);
		}

		return $this->meteringPointInputXmlsFromArray($resultArray);
	}


	/**
	 * @inheritDoc
	 * @throws Exception
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::serializePropertyConfiguration()
	 */
	public static function create(Console $console): ParserInterface {
		$console->writeLine('');
		$console->writeLine('Configuring XML parser', Console::COLOR_YELLOW);

		$timeZone = self::createTimeZoneConfig($console);

		$separatorThousands = $console->ask('Separator for groups of thousands in values. May be empty. Example: , for 12,040.01 cm');
		$separatorDecimals = $console->ask('Separator for decimals. Example: . for 142.3 cm');
		$formatsFilename = $console->ask('Filename for xml format definitions');

		$skipEmptyValueTag = $console->askWithDefault('Should parser ignore empty values in XML?', 'n');
		$skipEmptyValueTag = trim(strtolower($skipEmptyValueTag)) === 'y';

		$config = [
			'separatorThousands' => $separatorThousands,
			'separatorDecimals'  => $separatorDecimals,
			'formatsFilename'    => $formatsFilename,
			'skipEmptyValueTag'  => $skipEmptyValueTag,
			'timeZone'           => $timeZone
		];

		return new self($config);
	}


	/**
	 * @inheritDoc
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::serializePropertyConfiguration()
	 */
	public function serializeConfiguration(): string {
		$config = '';
		$config .= 'separatorThousands = "' . $this->separatorThousands . "\"\n";
		$config .= 'separatorDecimals = "' . $this->separatorDecimals . "\"\n";
		$config .= 'formatsFilename = "' . $this->formatsFilename . "\"\n";
		$config .= 'skipEmptyValueTag = ' . $this->skipEmptyValueTag ? 1 : 0 . "\n";
		$config .= 'timeZone = ' . $this->timeZone . "\n";

		return $config;
	}


	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return 'xml parser';
	}


	/**
	 * @inheritDoc
	 */
	public static function getHelp(): string {
		return 'For parsing data in XML format.';
	}


}
