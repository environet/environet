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
	 */
	public function __construct(array $config) {

		$this->separatorThousands = $config['separatorThousands'];
		$this->separatorDecimals = $config['separatorDecimals'];
		$this->formatsFilename = $config['formatsFilename'];

		$configurationsPath = SRC_PATH . '/conf/plugins/configurations/';
		$formatsPathname = $configurationsPath . $this->formatsFilename;
		$formats = file_get_contents($formatsPathname);
		$this->formats = JSON_decode($formats, true);

		if (!$this->formats) {
			throw new \Exception("Syntax error in json string of formats configuration file '$formatsPathname'.");			
		}

    parent::__construct($config);
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
	 * @param float $value measured value to convert
	 * @param string $symbol internal symbol for observed property
	 * @param string $unit unit in which $value is given
	 */
	private function convertUnitToBaseUnit(float &$value, string $symbol, string $unit) {
		$symbolParts = explode('_', $symbol);
		if (sizeof($symbolParts) > 0) {
			$symbol = $symbolParts[0];
		}

		$unit = strtolower($unit);

		if ($symbol == 'h') {
			if ($unit == "mm") {
				$value /= 10;
			} elseif ($unit == "m") {
				$value *= 100;
			}
		} elseif ($symbol == "Q") {
			// no sensible other units than m³/s
		} elseif ($symbol == "tw") {
			// no sensible other units than °C
		} elseif ($symbol == "P") {
			if ($unit == "cm" || $unit == "cm/h") {
				$value *= 10;
			} elseif ($unit == "m" || $unit == "m/h") {
				$value *= 1000;
			}
		} elseif ($symbol == "ta") {
			// no sensible other units than °C
		}
	}


	/**
	 * Returns one common element from xml tag hierarchy and strips it from format information which describes xml format.
	 *
	 * @param array $formats format information, including tag hierarchies for different parameters, which should be read from xml
	 * @return string The first common element of tag hierarchy, if any. If there is none, "" is returned.
	 */
	private function getAndStripOneCommonElement(array &$formats) : string {
		if (sizeof($formats) == 0) {
			return "";
		}
		if (sizeof($formats) == 1 && sizeof($formats[0]["Tag Hierarchy"]) == 0) {
			return "";
		}
		if (sizeof($formats) == 1 && sizeof($formats[0]["Tag Hierarchy"]) > 0) {
			return array_shift($formats[0]["Tag Hierarchy"]);
		}
		$difference = false;
		for ($i = 1; $i < sizeof($formats); ++$i) {
			if (sizeof($formats[$i]["Tag Hierarchy"]) == 0 || sizeof($formats[$i-1]["Tag Hierarchy"]) == 0 ||
				$formats[$i]["Tag Hierarchy"][0] != $formats[$i-1]["Tag Hierarchy"][0]) {
				$difference = true;
			}
		}
		if ($difference) {
			return "";
		}
		$result = $formats[0]["Tag Hierarchy"][0];
		for ($i = 0; $i < sizeof($formats); ++$i) {
			array_shift($formats[$i]["Tag Hierarchy"]);
		}
		return $result;
	}


	/**
	 * Recursive function to parse a xml tree to acquire values for given parameters from xml tree
	 *
	 * @param SimpleXMLElement $xml xml element to parse
	 * @param array $formats format of information to be gathered from xml, including tag hierarchies for different parameters
	 * @param array $resolved table of information found. 1st index is the entry if there are multiple, 2nd index is information, call with "[]"
	 * @param int $hierarchyCounter level of hierarchy, call with "0"
	 *
	 * @return array list of information gathered from xml
	 */
	private function parseIntoHierarchy(SimpleXMLElement $xml, array $formats, array $resolved, int $hierarchyCounter) : array {
		/*
		echo "-----------------------------------------\r\n";
		echo "parseIntoHierary called.\r\n";
		ob_start();
		var_dump($xml);
		echo "xml: " . ob_get_clean() . "\r\n";
		ob_start();
		var_dump($formats);
		echo "formats: " . ob_get_clean() . "\r\n";
		ob_start();
		var_dump($resolved);
		echo "resolved: " . ob_get_clean(). "\r\n";
		echo "hierarchyCounter: " . $hierarchyCounter . "\r\n";
		*/

		if ($hierarchyCounter > 10) {
			throw new \Exception("XML hierarchy deeper than 10");
		}

		if (sizeof($formats) == 0) {
			echo "Error condition 1: Call, but all information already resolved.";
			return [];
		}

		// get groups of common hierarchy
		$commonElements = [];
		do {
			$common = $this->getAndStripOneCommonElement($formats);
			if ($common != "") {
				array_push($commonElements, $common);
			}
		} while ($common != "");
		$xpathCommonElements = implode('/', $commonElements);

		// Finish condition 1: No common elements, but unresolved information
		if ($xpathCommonElements == "") {
			throw new \Exception("Unresolved information: " . $out);
		}

		// get groups
		$flatList = [];
		$groups = $xml->xpath($xpathCommonElements);
		//echo "xpathCommonElements: " . $xpathCommonElements . "\r\n";
		//echo "Number of groups: " . sizeof($groups) . "\r\n";

		if ($groups == null) {
			//var_dump($resolved);
			throw new \Exception("Given elements do not exist in file: " . $xpathCommonElements);
		}

		foreach ($groups as $group) {
			//ob_start();
			//var_dump($group);
			//echo "group: " . ob_get_clean() . "\r\n";
			// count elements and resolve those which are unique
			$nResolved = 0;
			$groupResolved = $resolved;
			$formatsNew = [];
			foreach ($formats as $format) {
				//echo "format.Parameter: " . $format["Parameter"] . "\r\n";
				$xpath = implode('/', $format["Tag Hierarchy"]);
				//echo "   xpath: " . $xpath . "\r\n";
				if ($xpath != "") {
					// desired information is sub-item of group
					$subXml = $group->xpath($xpath);
				} else {
					// desired information is group itself
					$subXml = $group;
					if ($format["Attribute"] != "" && $subXml->getName() == end($commonElements)) {
						// desired information is attribute of group-defining tag
						$item = [];
						$item["Type"] = $format["Parameter"];
						$item["Value"] = $subXml[0][$format["Attribute"]]->__toString();
						$item["Format"] = $format["Value"];
						$item["Unit"] = $format["Unit"];
						array_push($groupResolved, $item);
						++$nResolved;
						//echo "resolved by attribute1: " . $item["Value"] . "\r\n";
					}
				}
				//ob_start();
				//var_dump($subXml);
				//echo "   sizeof(subXml)=" . sizeof($subXml) . "\r\n";
				//echo "subXml: " . ob_get_clean() . "\r\n";
				if ($subXml == null) {
					//var_dump($resolved);
					//var_dump($groupResolved);
					//echo "   value=" . $format["Value"] . "\r\n";
					//var_dump($format["optional"]);
					if ($format["optional"]) {
						continue;
					}
					throw new \Exception("Given elements do not exist in file: " . $xpath);
				}
				if (sizeof($subXml) == 0) {
					// do nothing
					//echo "do nothing.\r\n";
				} elseif (sizeof($subXml) == 1) {
					$item = [];
					$item["Type"] = $format["Parameter"];
					if ($format["Attribute"] == "") {
						$item["Value"] = $subXml[0]->__toString();
						if ($item["Value"] === "") {
							$item["Value"] = "0";
						}
					} else {
						//var_dump($subXml[0]);
						//$item["Value"] = $subXml[0]->attributes()[$format["Attribute"]];
						$item["Value"] = $subXml[0][$format["Attribute"]]->__toString();
						//echo "resolved by attribute2: " . $item["Value"] . "\r\n";
					}
					$item["Format"] = $format["Value"];
					$item["Unit"] = $format["Unit"];
					array_push($groupResolved, $item);
					++$nResolved;
					//echo "resolved ".$item["Type"].", value=".$item["Value"]."\r\n";
				} else {
					array_push($formatsNew, $format);
					//echo "not yet resolved.\r\n";
				}
			}

			if (sizeof($formatsNew) > 1) {
				// do recursion
				//echo "do recursion\r\n";
				$flatList = array_merge($flatList, $this->parseIntoHierarchy($group, $formatsNew, $groupResolved, $hierarchyCounter + 1));
			} else {
				// Finish condition 3: Success
				// all information available. Return flat list entry from resolved
				//echo "all resolved.\r\n";
				array_push($flatList, $groupResolved);
			}
		}	// group

		return $flatList;
	}


	/**
	 * Get internal symbol for observed property from external symbol. Conversion between symbols is given by
	 * variable conversion information.
	 *
	 * @param array $conversions conversion information read from json file by transport.
	 * @param string $variableName name of variable definition for observed property. E.g. "OBS"
	 * @param string $symbol external symbol for observed property
	 *
	 * @return string internal symbol for observed property
	 */
	private function getInternalObservedPropertySymbol(array $observedPropertyConversions, string $variableName, string $symbol) : string {
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
	 * @param array $list list of items.
	 * @param string $parameterName name of parameter
	 * @param string $parameterValue value of parameter
	 *
	 * @return array items found, "[]" if none found.
	 */
	private function getParameter(array $list, string $parameterName, string $parameterValue) : array {
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
	 * @param array $list list to delete items from
	 * @param string $parameterName name of parameter
	 * @param string $parameterValue value for parameter
	 */
	private function delete(array &$list, string $parameterName, string $parameterValue) {
		foreach ($list as $key => &$item) {
			if (array_key_exists($parameterName, $item) && $item[$parameterName] == $parameterValue) {
				unset($list[$key]);
			}
		}
		$list = array_values($list);
	}


	/**
	 * Check whether a given value for a parameter exists in list. List item is an associative array in which parameter names are keys.
	 *
	 * @param array $list list to check
	 * @param string $parameterName name of parameter
	 *
	 * @return bool true, if item is existing in list
	 */
	private function exists(array $list, string $parameterName, string $parameterValue) : bool {
		$exists = false;
		foreach ($list as $item) {
			if (array_key_exists($parameterName, $item) && $item[$parameterName] == $parameterValue) {
				$exists = true;
				break;
			}
		}
		return $exists;
	}


	/**
	 * Assembles date from componentes like day, month, year, hour and minute
	 *
	 * @param array $entry list of parsed properties, in which separate items for day, month, etc... may occur. Separate items are joined to a
	 *                     "DateTime" item and deleted from $entry. "DateTime" has time format as given by API_TIME_FORMAT_STRING
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
			"Type" => "DateTime",
			"Value" => "",
			"Format" => self::API_TIME_FORMAT_STRING,
			"Unit" => null
		];

		if ($Year && $Month && $Day && $Hour && $Minute)
		{
			$t = mktime(strval($Hour["Value"]), strval($Minute["Value"]), 0,
				strval($Month["Value"]), strval($Day["Value"]), strval($Year["Value"]));
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
		}
		else if ($Date && $Time)
		{
			$date = DateTime::createFromFormat($Date["Format"].' '.$Time["Format"], $Date["Value"].' '.$Time["Value"], $this->getTimeZone());
			if (!$date) {
				echo("Warning: Invalid date or time format: " . $Date["Format"] . " - " . $Date["Value"]
					. " -- " . $Time["Format"] . " - " . $Time["Value"] . ". Replaced with 1970-01-01\r\n");
				$date = new DateTime('1970-01-01T00:00:00Z');
			}
			$date->setTimezone(new DateTimeZone('UTC'));
			$result["Value"] = $date->format(self::API_TIME_FORMAT_STRING);
			$this->delete($entry, "Type", "Date");
			$this->delete($entry, "Type", "Time");
		}
		else if ($DateTime)
		{
			$date = DateTime::createFromFormat($DateTime["Format"], $DateTime["Value"], $this->getTimeZone());
			if (!$date) {
				echo("Warning: Invalid datetime format: " . $DateTime["Format"] . " --- " . $DateTime["Value"] . ". Replaced with 1970-01-01\r\n");
				$date = new DateTime('1970-01-01T00:00:00Z');
			}
			$date->setTimezone(new DateTimeZone('UTC'));
			$result["Value"] = $date->format(self::API_TIME_FORMAT_STRING);
			$this->delete($entry, "Type", "DateTime");
		} else {
			throw new \Exception("Incomplete date");
		}
		array_push($entry, $result);
	}


	/**
	 * Assemble dates in whole list of entries.
	 *
	 * @param array $flatList list of parsed information. Entries are themselve lists of parsed parameters.
	 * @see assembleDate
	 */
	private function assembleDates(array &$flatList) {
		foreach ($flatList as &$entry) {
			$this->assembleDate($entry);
		}
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
	private function convertValue(array &$entry) : bool {
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
							"Type" => "ObservedPropertyUnit",
							"Value" => $item["Unit"],
							"Format" => null,
							"Unit" => null,
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
	 * @inheritDoc
	 * @throws CreateInputXmlException
	 */
	public function parse(Resource $resource): array {

		//var_dump($resource->meta);
		//echo $resource->contents;
		echo "Received " . strlen($resource->contents) . " characters.\r\n";


		$resource->contents = str_replace("xlink:href", "href", $resource->contents);	// Workaround for WaterML 2.0

		//$resource->contents = $this->getExampleXMLBMLRT();
		//$resource->contents = $this->getExampleXMLLfU();
		//$resource->contents = $this->getExampleXMLARSO();

		libxml_use_internal_errors(TRUE); // this turns off spitting parsing errors on screen
		$xml = new SimpleXMLElement($resource->contents);
		$ns = $xml->getDocNamespaces();
		$xml->registerXPathNamespace('def', array_values($ns)[0]);

		$formats = $this->formats;

		// strip top-level element from formats
		$topLevel = $this->getAndStripOneCommonElement($formats);
		if ($topLevel == "") {
			throw new \Exception("XML definition does not have a top-level element");
		}

		$flatList = $this->parseIntoHierarchy($xml, $formats, [], 0);
		//var_dump($flatList[0]);

		// replace external observed property symbols and add missing information from API-Call (Monitoring Point or Observed Property Symbol)
		if ($resource->meta) {
			$newEntries = [];
			foreach ($flatList as $key => &$entry) {
				$mp = $this->getParameter($entry, "Type", "MonitoringPoint");
				if (!$mp) {
					// Add monitoring point national code from API-Call
					$elem = [
						"Type" => "MonitoringPoint",
						"Value" => $resource->meta["MonitoringPointNCDs"][0],
						"Format" => null,
						"Unit" => null,
					];
					array_push($entry, $elem);
				}

				$obs = $this->getParameter($entry, "Type", "ObservedPropertySymbol");
				if ($obs) {
					// convert external (in-file) symbol to internal symbol
					$symbolNameInFile = $obs["Value"];
					$variableName = $obs["Format"];
					if ($symbolNameInFile && $variableName) {
						//var_dump($symbolNameInFile);
						//var_dump($variableName);
						$symbol = $this->getInternalObservedPropertySymbol($resource->meta["observedPropertyConversions"], $variableName, $symbolNameInFile);
						if ($symbol) {
							$this->delete($entry, "Type", "ObservedPropertySymbol");
							$obs["Value"] = $symbol;
							array_push($entry, $obs);
						} else {
							unset($flatList[$key]);   // Delete whole entry as observed property was not found
						}
					}
				} else {
					if (sizeof($resource->meta["ObservedPropertySymbols"]) == 1) {
						// Only one observed property by call: Add observed property symbol from API-Call
						$elem = [
							"Type" => "ObservedPropertySymbol",
							"Value" => $resource->meta["ObservedPropertySymbols"][0],
							"Format" => null,
							"Unit" => null,
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
									"Type" => "ObservedPropertySymbol",
									"Value" => $prop,
									"Format" => null,
									"Unit" => null,
								];
								array_push($newEntry, $elem);
								// delete all occurrences of ObservedPropertyValue with wrong symbol
								foreach ($newEntry as $newenkey => &$newenval) {
									if ($newenval["Type"] == "ObservedPropertyValue" && $newenval["Format"] != $prop) {
										unset($newEntry[$newenkey]);
									}
								}
								$newEntry = array_values($newEntry);
								array_push($newEntries, $newEntry);
								++$count;
							}
						}
						if ($count == 0) {
							throw new \Exception("No value for any observed property in entry.");
						}
						unset($flatList[$key]);
					}
				}
			}
			$flatList = array_values($flatList);
			$flatList = array_merge($flatList, $newEntries);
		}

		// delete entries which do not fit to API-call (extra monitoring points, extra observed properties)
		if ($resource->meta && !$resource->meta["keepExtraData"]) {
			foreach ($flatList as $key => &$entry) {
				$mp = $this->getParameter($entry, "Type", "MonitoringPoint");
				$obs = $this->getParameter($entry, "Type", "ObservedPropertySymbol");
				//echo "mp: " .$mp["Value"] . ", obs: ". $obs["Value"] ."\r\n";
				if (!in_array($mp["Value"], $resource->meta["MonitoringPointNCDs"]) || !in_array($obs["Value"], $resource->meta["ObservedPropertySymbols"])) {
					unset($flatList[$key]);
				}
			}
			$flatList = array_values($flatList);
		}

		//die(var_dump($flatList));

		$this->assembleDates($flatList);
		$this->convertValues($flatList);

		//var_dump($flatList);

		//ob_start();
		//var_dump($flatList);
		//echo "flatList: " . ob_get_clean() . "\r\n";

		$resultArray = [];
		$properties = [];
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
			$unit = $this->getParameter($line, "Type", "ObservedPropertyUnit")["Value"];

			$resultArray[$mp][$obs] = array_merge(
				$resultArray[$mp][$obs],
				[
					[
						'time' => $time,
						'value' => $value,
						//'unit' => $unit,
					]
				]
			);

		}

		/*
		ini_set('xdebug.var_display_max_depth', '10');
		ini_set('xdebug.var_display_max_children', '256');
		ini_set('xdebug.var_display_max_data', '1024');

		ob_start();
		var_dump($resultArray);
		echo "resultArray: " . ob_get_clean() . "\r\n";
		*/

		//$tmp = $this->meteringPointInputXmlsFromArray($resultArray);
		//var_dump($tmp);

		return $this->meteringPointInputXmlsFromArray($resultArray);
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
			array_push($payloads, $creator->generateXml(new InputXmlData($mPointId, $this->inputXmlDataFromArray($properties))));
		}

		return $payloads;
	}


	/**
	 * Create XML data from the observation properties input array.
	 *
	 * @param array $propertiesArray
	 *
	 * @return array
	 * @see InputXmlPropertyData
	 */
	private function inputXmlDataFromArray(array $propertiesArray) {
		return array_map(function ($key, $value) {
			return new InputXmlPropertyData($key, $value);
		}, array_keys($propertiesArray), $propertiesArray);
	}


	/**
	 * @inheritDoc
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::serializePropertyConfiguration()
	 */
	public static function create(Console $console): ParserInterface {
		$console->writeLine('');
		$console->writeLine('Configuring XML parser', Console::COLOR_YELLOW);

		$timeZone = self::createTimeZoneConfig($console);

		$separatorThousands = $console->ask('Separator for groups of thousands in values. May be empty. Example: , for 12,040.01 cm');
		$separatorDecimals = $console->ask('Separator for decimals. Example: . for 142.3 cm');
		$formatsFilename = $console->ask('Filename for xml format definitions');

		$config = [
			'separatorThousands' => $separatorThousands,
			'separatorDecimals' => $separatorDecimals,
			'formatsFilename' => $formatsFilename,
			'timeZone' => $timeZone
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


	/**
	 * Return an example xml file for data provider "BMLRT" (Austrian hydrological service)
	 *
	 * @return string example xml
	 */
	private function getExampleXMLBMLRT(): string {
		return <<<'XML'
<?xml version='1.0' encoding="UTF-8" ?>
<wfs:FeatureCollection
   xmlns:ms="http://mapserver.gis.umn.edu/mapserver"
   xmlns:wfs="http://www.opengis.net/wfs"
   xmlns:gml="http://www.opengis.net/gml"
   xmlns:ogc="http://www.opengis.net/ogc"
   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
   xsi:schemaLocation="http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.0.0/WFS-basic.xsd 
                       http://mapserver.gis.umn.edu/mapserver https://gis.lfrz.gv.at/wmsgw/?key=2993ead1af652c1e809263930333a2fb&amp;SERVICE=WFS&amp;VERSION=1.0.0&amp;REQUEST=DescribeFeatureType&amp;TYPENAME=pegelaktuell&amp;OUTPUTFORMAT=XMLSCHEMA">
	<gml:boundedBy>
		<gml:Box srsName="EPSG:31287">
			<gml:coordinates>112844.000000,279413.000000 680586.000000,565345.000000</gml:coordinates>
		</gml:Box>
	</gml:boundedBy>
	<gml:featureMember>
		<ms:pegelaktuell fid="pegelaktuell.15">
			<gml:boundedBy>
				<gml:Box srsName="EPSG:31287">
					<gml:coordinates>
						372523.000000,316503.000000 372523.000000,316503.000000
					</gml:coordinates>
				</gml:Box>
			</gml:boundedBy>
			<ms:msGeometry>
				<gml:Point srsName="EPSG:31287">
					<gml:coordinates>372523.000000,316503.000000</gml:coordinates>
				</gml:Point>
			</ms:msGeometry>
			<ms:gid>15</ms:gid>
			<ms:dbmsnr>2001001</ms:dbmsnr>
			<ms:hzbnr>212324</ms:hzbnr>
			<ms:gewasser>Drau</ms:gewasser>
			<ms:hd>Kärnten</ms:hd>
			<ms:messstelle>Oberdrauburg</ms:messstelle>
			<ms:land/>
			<ms:internet>
				https://info.ktn.gv.at/asp/hydro/daten/QP_Oberdrauburg.gif
			</ms:internet>
			<ms:parameter>Q</ms:parameter>
			<ms:herkunft>F</ms:herkunft>
			<ms:wert>134</ms:wert>
			<ms:zp>2020-06-23 16:00:00</ms:zp>
			<ms:typ>0</ms:typ>
			<ms:farbe>2</ms:farbe>
			<ms:datum>2020-06-23 17:20:01</ms:datum>
			<ms:symbol>3</ms:symbol>
			<ms:gesamtcode>230</ms:gesamtcode>
			<ms:old_geom/>
			<ms:geol>12,973611</ms:geol>
			<ms:geob>46,748056</ms:geob>
			<ms:wertw_cm>164.0</ms:wertw_cm>
			<ms:prognose>false</ms:prognose>
		</ms:pegelaktuell>
	</gml:featureMember>
	<gml:featureMember>
		<ms:pegelaktuell fid="pegelaktuell.16">
			<gml:boundedBy>
			</gml:boundedBy>
			<ms:msGeometry>
			</ms:msGeometry>
			<ms:gid>16</ms:gid>
			<ms:dbmsnr>2001003</ms:dbmsnr>
			<ms:hzbnr>212357</ms:hzbnr>
			<ms:gewasser>Drau</ms:gewasser>
			<ms:hd>Kärnten</ms:hd>
			<ms:messstelle>Sachsenburg</ms:messstelle>
			<ms:land/>
			<ms:internet>
			</ms:internet>
			<ms:parameter>Q</ms:parameter>
			<ms:herkunft>F</ms:herkunft>
			<ms:wert>138</ms:wert>
			<ms:zp>2020-06-23 16:00:00</ms:zp>
			<ms:typ>0</ms:typ>
			<ms:farbe>2</ms:farbe>
			<ms:datum>2020-06-23 17:20:01</ms:datum>
			<ms:symbol>3</ms:symbol>
			<ms:gesamtcode>230</ms:gesamtcode>
			<ms:old_geom/>
			<ms:geol>13,350000</ms:geol>
			<ms:geob>46,827500</ms:geob>
			<ms:wertw_cm>138.0</ms:wertw_cm>
			<ms:prognose>false</ms:prognose>
		</ms:pegelaktuell>
	</gml:featureMember>
</wfs:FeatureCollection>
XML;
	}


	/**
	 * Return an example xml file for data provider "LfU" (German hydrological service)
	 *
	 * @return string example xml
	 */
	private function getExampleXMLLfU(): string {
		return <<<'XML'
<hnd-daten>
<messstelle>
<nummer>10032009</nummer>
	<messwert>
		<datum>
			<jahr>2020</jahr>
			<monat>06</monat>
			<tag>17</tag>
			<stunde>00</stunde>
			<minute>00</minute>
		</datum>
		<wert>197</wert>
	</messwert>
	<messwert>
		<datum>
			<jahr>2020</jahr>
			<monat>06</monat>
			<tag>17</tag>
			<stunde>23</stunde>
			<minute>45</minute>
		</datum>
		<wert>234</wert>
	</messwert>
</messstelle>
<messstelle>
<nummer>10026301</nummer>
	<messwert>
		<datum>
			<jahr>2020</jahr>
			<monat>06</monat>
			<tag>17</tag>
			<stunde>00</stunde>
			<minute>00</minute>
		</datum>
		<wert>19,7</wert>
	</messwert>
	<messwert>
		<datum>
			<jahr>2020</jahr>
			<monat>06</monat>
			<tag>17</tag>
			<stunde>23</stunde>
			<minute>45</minute>
		</datum>
		<wert>23,4</wert>
	</messwert>
</messstelle>
</hnd-daten>
XML;
	}


	/**
	 * Return an example xml file for data provider "ARSO" (Slowenian hydrological and meteorological service)
	 *
	 * @return string example xml
	 */
	private function getExampleXMLARSO(): string {
		return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<arsopodatki verzija="1.3">
<vir>Agencija RS za okolje</vir>
<predlagan_zajem>5 minut &#269;ez polno uro ali pol ure</predlagan_zajem>
<predlagan_zajem_perioda>30 min</predlagan_zajem_perioda>
<datum_priprave>2020-06-25 09:31</datum_priprave>
<postaja sifra="1060" ge_dolzina="16.000253" ge_sirina="46.68151" kota_0="202.34"><reka>Mura</reka><merilno_mesto>Gornja Radgona</merilno_mesto><ime_kratko>Mura - Gor. Radgona</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>112</vodostaj><pretok>150</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>16.2</temp_vode><prvi_vv_pretok>600</prvi_vv_pretok><drugi_vv_pretok>905</drugi_vv_pretok><tretji_vv_pretok>1180</tretji_vv_pretok></postaja>
<postaja sifra="1070" ge_dolzina="16.059244" ge_sirina="46.648821" kota_0="193.65"><reka>Mura</reka><merilno_mesto>Petanjci</merilno_mesto><ime_kratko>Mura - Petanjci</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>168</vodostaj><pretok>152</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>16.2</temp_vode><prvi_vv_pretok>650</prvi_vv_pretok><drugi_vv_pretok>965</drugi_vv_pretok><tretji_vv_pretok>1250</tretji_vv_pretok></postaja>
<postaja sifra="1100" ge_dolzina="16.026247" ge_sirina="46.711322" kota_0="205.93"><reka>Kučnica</reka><merilno_mesto>Cankova</merilno_mesto><ime_kratko>Kučnica - Cankova</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>79</vodostaj><pretok>0.05</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>17.2</temp_vode><prvi_vv_pretok>10</prvi_vv_pretok><drugi_vv_pretok>21</drugi_vv_pretok><tretji_vv_pretok>34</tretji_vv_pretok></postaja>
<postaja sifra="1140" ge_dolzina="16.236726" ge_sirina="46.518911" kota_0="169.37"><reka>Ščavnica</reka><merilno_mesto>Pristava</merilno_mesto><ime_kratko>Ščavnica - Pristava</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>48</vodostaj><pretok>0.44</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>17.7</temp_vode><prvi_vv_pretok>36</prvi_vv_pretok><drugi_vv_pretok>50</drugi_vv_pretok><tretji_vv_pretok>64</tretji_vv_pretok></postaja>
<postaja sifra="1165" ge_dolzina="16.032468" ge_sirina="46.810437" kota_0="232.29"><reka>Ledava</reka><merilno_mesto>Nuskova</merilno_mesto><ime_kratko>Ledava - Nuskova</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>53</vodostaj><pretok>0.09</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>16.5</temp_vode><prvi_vv_pretok>19</prvi_vv_pretok><drugi_vv_pretok>31</drugi_vv_pretok><tretji_vv_pretok>48</tretji_vv_pretok></postaja>
<postaja sifra="1220" ge_dolzina="16.142077" ge_sirina="46.678364" kota_0="191.35"><reka>Ledava</reka><merilno_mesto>Polana</merilno_mesto><ime_kratko>Ledava - Polana</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>33</vodostaj><pretok>0.15</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>19.6</temp_vode><prvi_vv_pretok>31.5</prvi_vv_pretok><drugi_vv_pretok>45</drugi_vv_pretok><tretji_vv_pretok>65</tretji_vv_pretok></postaja>
<postaja sifra="1260" ge_dolzina="16.482559" ge_sirina="46.535559" kota_0="154.31"><reka>Ledava</reka><merilno_mesto>Čentiba</merilno_mesto><ime_kratko>Ledava - Čentiba</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>131</vodostaj><pretok>2.2</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>18.4</temp_vode><prvi_vv_pretok>55</prvi_vv_pretok><drugi_vv_pretok>72</drugi_vv_pretok><tretji_vv_pretok>88</tretji_vv_pretok></postaja>
<postaja sifra="1300" ge_dolzina="16.191113" ge_sirina="46.686646" kota_0="190.55"><reka>Martjanski potok</reka><merilno_mesto>Martjanci</merilno_mesto><ime_kratko>Martjanski p. - Martjanci</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>37</vodostaj><pretok>0.01</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.8</temp_vode><prvi_vv_pretok>7.4</prvi_vv_pretok><drugi_vv_pretok>12.8</drugi_vv_pretok><tretji_vv_pretok>21</tretji_vv_pretok></postaja>
<postaja sifra="1312" ge_dolzina="16.398339" ge_sirina="46.685606" kota_0="182.63"><reka>Kobiljski potok</reka><merilno_mesto>Kobilje</merilno_mesto><ime_kratko>Kobiljski p. - Kobilje</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>70</vodostaj><pretok>0</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>18.5</temp_vode><prvi_vv_pretok>16</prvi_vv_pretok><drugi_vv_pretok>22</drugi_vv_pretok><tretji_vv_pretok>30</tretji_vv_pretok></postaja>
<postaja sifra="1335" ge_dolzina="16.317112" ge_sirina="46.770293" kota_0="229.68"><reka>Ivanjševski potok</reka><merilno_mesto>Središče</merilno_mesto><ime_kratko>Ivanjševski p. - Središče</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>22</vodostaj><pretok>0.01</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.1</temp_vode><prvi_vv_pretok>2</prvi_vv_pretok><drugi_vv_pretok>3.5</drugi_vv_pretok><tretji_vv_pretok>4.9</tretji_vv_pretok></postaja>
<postaja sifra="1355" ge_dolzina="16.337982" ge_sirina="46.814516" kota_0="224.82"><reka>Velika Krka</reka><merilno_mesto>Hodoš</merilno_mesto><ime_kratko>Velika Krka - Hodoš</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>7</vodostaj><pretok>0.02</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>17.5</temp_vode><prvi_vv_pretok>21</prvi_vv_pretok><drugi_vv_pretok>37</drugi_vv_pretok><tretji_vv_pretok>50</tretji_vv_pretok></postaja>
<postaja sifra="2005" ge_dolzina="14.988709" ge_sirina="46.598208" kota_0="333.58"><reka>Drava</reka><merilno_mesto>Črneče</merilno_mesto><ime_kratko>Drava - Črneče</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>556</vodostaj><pretok></pretok><pretok_znacilni></pretok_znacilni><temp_vode>15.0</temp_vode><prvi_vv_pretok>860</prvi_vv_pretok><drugi_vv_pretok>1200</drugi_vv_pretok><tretji_vv_pretok>1450</tretji_vv_pretok></postaja>
<postaja sifra="2110" ge_dolzina="15.872409" ge_sirina="46.417219" kota_0="214.65"><reka>Drava</reka><merilno_mesto>Ptuj</merilno_mesto><ime_kratko>Drava - Ptuj</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>511</vodostaj><pretok>455</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode></temp_vode><prvi_vv_pretok>1300</prvi_vv_pretok><drugi_vv_pretok>1580</drugi_vv_pretok><tretji_vv_pretok>1700</tretji_vv_pretok></postaja>
<postaja sifra="2150" ge_dolzina="16.001075" ge_sirina="46.371291" kota_0="201.49"><reka>Drava</reka><merilno_mesto>Borl</merilno_mesto><ime_kratko>Drava - Borl</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>66</vodostaj><pretok>13.6</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>16.7</temp_vode><prvi_vv_pretok>500</prvi_vv_pretok><drugi_vv_pretok>1200</drugi_vv_pretok><tretji_vv_pretok>1500</tretji_vv_pretok></postaja>
<postaja sifra="2160" ge_dolzina="16.041742" ge_sirina="46.388654" kota_0="197.86"><reka>Drava</reka><merilno_mesto>Zavrč</merilno_mesto><ime_kratko>Drava - Zavrč</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>15</vodostaj><pretok>14.8</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>16.5</temp_vode><prvi_vv_pretok>500</prvi_vv_pretok><drugi_vv_pretok>1200</drugi_vv_pretok><tretji_vv_pretok>1500</tretji_vv_pretok></postaja>
<postaja sifra="2220" ge_dolzina="14.853217" ge_sirina="46.470732" kota_0="573.45"><reka>Meža</reka><merilno_mesto>Črna</merilno_mesto><ime_kratko>Meža -  Črna</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>55</vodostaj><pretok>1.50</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>11.3</temp_vode><prvi_vv_pretok>30</prvi_vv_pretok><drugi_vv_pretok>39</drugi_vv_pretok><tretji_vv_pretok>60</tretji_vv_pretok></postaja>
<postaja sifra="2250" ge_dolzina="15.030756" ge_sirina="46.578095" kota_0="334"><reka>Meža</reka><merilno_mesto>Otiški Vrh</merilno_mesto><ime_kratko>Meža - Otiški vrh</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>99</vodostaj><pretok>7.94</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>15.2</temp_vode><prvi_vv_pretok>89</prvi_vv_pretok><drugi_vv_pretok>136</drugi_vv_pretok><tretji_vv_pretok>316</tretji_vv_pretok></postaja>
<postaja sifra="2372" ge_dolzina="15.152306" ge_sirina="46.456958" kota_0="507.59"><reka>Mislinja</reka><merilno_mesto>Dovže</merilno_mesto><ime_kratko>Mislinja - Dovže</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>37</vodostaj><pretok>1.05</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>12.5</temp_vode><prvi_vv_pretok>30</prvi_vv_pretok><drugi_vv_pretok>40</drugi_vv_pretok><tretji_vv_pretok>70</tretji_vv_pretok></postaja>
<postaja sifra="2390" ge_dolzina="15.042074" ge_sirina="46.567852" kota_0="344.75"><reka>Mislinja</reka><merilno_mesto>Otiški Vrh</merilno_mesto><ime_kratko>Mislinja - Otiški Vrh</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>55</vodostaj><pretok>2.43</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>14.8</temp_vode><prvi_vv_pretok>70</prvi_vv_pretok><drugi_vv_pretok>100</drugi_vv_pretok><tretji_vv_pretok>205</tretji_vv_pretok></postaja>
<postaja sifra="2420" ge_dolzina="15.077353" ge_sirina="46.504732" kota_0="404.86"><reka>Suhodolnica</reka><merilno_mesto>Stari Trg</merilno_mesto><ime_kratko>Suhodolnica - Stari trg</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>20</vodostaj><pretok>0.55</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>13.7</temp_vode><prvi_vv_pretok>31</prvi_vv_pretok><drugi_vv_pretok>38</drugi_vv_pretok><tretji_vv_pretok>51</tretji_vv_pretok></postaja>
<postaja sifra="2432" ge_dolzina="15.168566" ge_sirina="46.612517" kota_0="325.757"><reka>Bistrica</reka><merilno_mesto>Muta</merilno_mesto><ime_kratko>Bistrica - Muta</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>60</vodostaj><pretok>3.02</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>13.6</temp_vode><prvi_vv_pretok>30</prvi_vv_pretok><drugi_vv_pretok>39</drugi_vv_pretok><tretji_vv_pretok>49</tretji_vv_pretok></postaja>
<postaja sifra="2530" ge_dolzina="15.423649" ge_sirina="46.558708" kota_0="296"><reka>Radoljna</reka><merilno_mesto>Ruta</merilno_mesto><ime_kratko>Radoljna - Ruta</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>116</vodostaj><pretok>1.48</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>13.2</temp_vode><prvi_vv_pretok>30</prvi_vv_pretok><drugi_vv_pretok>65</drugi_vv_pretok><tretji_vv_pretok>77</tretji_vv_pretok></postaja>
<postaja sifra="2600" ge_dolzina="15.3882" ge_sirina="46.38017" kota_0="404.97"><reka>Dravinja</reka><merilno_mesto>Zreče</merilno_mesto><ime_kratko>Dravinja - Zreče</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>70</vodostaj><pretok>0.35</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>13.1</temp_vode><prvi_vv_pretok>9</prvi_vv_pretok><drugi_vv_pretok>15</drugi_vv_pretok><tretji_vv_pretok>24</tretji_vv_pretok></postaja>
<postaja sifra="2620" ge_dolzina="15.498547" ge_sirina="46.301854" kota_0="266.46"><reka>Dravinja</reka><merilno_mesto>Loče</merilno_mesto><ime_kratko>Dravinja - Loče</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>138</vodostaj><pretok>1.06</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>16.3</temp_vode><prvi_vv_pretok>30</prvi_vv_pretok><drugi_vv_pretok>56</drugi_vv_pretok><tretji_vv_pretok>69</tretji_vv_pretok></postaja>
<postaja sifra="2640" ge_dolzina="15.675295" ge_sirina="46.319582" kota_0="240.22"><reka>Dravinja</reka><merilno_mesto>Makole</merilno_mesto><ime_kratko>Dravinja - Makole</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj></vodostaj><pretok></pretok><pretok_znacilni></pretok_znacilni><temp_vode></temp_vode><prvi_vv_pretok>47</prvi_vv_pretok><drugi_vv_pretok>80</drugi_vv_pretok><tretji_vv_pretok>140</tretji_vv_pretok></postaja>
<postaja sifra="2652" ge_dolzina="15.907876" ge_sirina="46.36865" kota_0="209.04"><reka>Dravinja</reka><merilno_mesto>Videm</merilno_mesto><ime_kratko>Dravinja - Videm</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>102</vodostaj><pretok>2.46</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>19.0</temp_vode><prvi_vv_pretok>120</prvi_vv_pretok><drugi_vv_pretok>190</drugi_vv_pretok><tretji_vv_pretok>240</tretji_vv_pretok></postaja>
<postaja sifra="2667" ge_dolzina="15.492589" ge_sirina="46.339628" kota_0="282.89"><reka>Oplotnica</reka><merilno_mesto>Perovec</merilno_mesto><ime_kratko>Oplotnica - Perovec</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>83</vodostaj><pretok>0.97</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>14.0</temp_vode><prvi_vv_pretok>30</prvi_vv_pretok><drugi_vv_pretok>35</drugi_vv_pretok><tretji_vv_pretok>42</tretji_vv_pretok></postaja>
<postaja sifra="2693" ge_dolzina="15.65561" ge_sirina="46.337351" kota_0="241.67"><reka>Ložnica</reka><merilno_mesto>Spodnja Ložnica</merilno_mesto><ime_kratko>Ložnica - Sp.Ložnica</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>86</vodostaj><pretok>0.42</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>19.7</temp_vode><prvi_vv_pretok>50</prvi_vv_pretok><drugi_vv_pretok>75</drugi_vv_pretok><tretji_vv_pretok>90</tretji_vv_pretok></postaja>
<postaja sifra="2719" ge_dolzina="15.883341" ge_sirina="46.332777" kota_0="223.24"><reka>Rogatnica</reka><merilno_mesto>Podlehnik</merilno_mesto><ime_kratko>Rogatnica - Podlehnik</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>90</vodostaj><pretok>0.03</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>17.4</temp_vode><prvi_vv_pretok>22</prvi_vv_pretok><drugi_vv_pretok>25</drugi_vv_pretok><tretji_vv_pretok>45</tretji_vv_pretok></postaja>
<postaja sifra="2754" ge_dolzina="15.882973" ge_sirina="46.363968" kota_0="214.07"><reka>Polskava</reka><merilno_mesto>Tržec</merilno_mesto><ime_kratko>Polskava - Tržec</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>84</vodostaj><pretok>0.37</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>20.6</temp_vode><prvi_vv_pretok>37</prvi_vv_pretok><drugi_vv_pretok>49</drugi_vv_pretok><tretji_vv_pretok>62</tretji_vv_pretok></postaja>
<postaja sifra="2830" ge_dolzina="15.686378" ge_sirina="46.599749" kota_0="250.27"><reka>Pesnica</reka><merilno_mesto>Ranca</merilno_mesto><ime_kratko>Pesnica - Ranca</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>48</vodostaj><pretok>0.11</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>18.1</temp_vode><prvi_vv_pretok>20</prvi_vv_pretok><drugi_vv_pretok>41</drugi_vv_pretok><tretji_vv_pretok>57</tretji_vv_pretok></postaja>
<postaja sifra="2880" ge_dolzina="15.873514" ge_sirina="46.557092" kota_0="225.46"><reka>Pesnica</reka><merilno_mesto>Gočova</merilno_mesto><ime_kratko>Pesnica - Gočova</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>103</vodostaj><pretok>0.20</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>19.6</temp_vode><prvi_vv_pretok>35</prvi_vv_pretok><drugi_vv_pretok>55</drugi_vv_pretok><tretji_vv_pretok>70</tretji_vv_pretok></postaja>
<postaja sifra="2900" ge_dolzina="16.03889" ge_sirina="46.414433" kota_0="201.85"><reka>Pesnica</reka><merilno_mesto>Zamušani</merilno_mesto><ime_kratko>Pesnica - Zamušani</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>86</vodostaj><pretok>0.63</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>17.3</temp_vode><prvi_vv_pretok>70</prvi_vv_pretok><drugi_vv_pretok>100</drugi_vv_pretok><tretji_vv_pretok>120</tretji_vv_pretok></postaja>
<postaja sifra="3014" ge_dolzina="13.790111" ge_sirina="46.48963" kota_0="793.94"><reka>Sava Dolinka</reka><merilno_mesto>Kranjska Gora</merilno_mesto><ime_kratko>Sava Dol. - Kranjska Gora</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>145</vodostaj><pretok>1.49</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>8.3</temp_vode><prvi_vv_pretok>18</prvi_vv_pretok><drugi_vv_pretok>24</drugi_vv_pretok><tretji_vv_pretok>60</tretji_vv_pretok></postaja>
<postaja sifra="3060" ge_dolzina="14.05902" ge_sirina="46.434212" kota_0="564.81"><reka>Sava Dolinka</reka><merilno_mesto>Jesenice</merilno_mesto><ime_kratko>Sava Dol. - Jesenice</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>77</vodostaj><pretok></pretok><pretok_znacilni></pretok_znacilni><temp_vode>8.8</temp_vode><prvi_vv_pretok>135</prvi_vv_pretok><drugi_vv_pretok>165</drugi_vv_pretok><tretji_vv_pretok>223</tretji_vv_pretok></postaja>
<postaja sifra="3080" ge_dolzina="14.139295" ge_sirina="46.367908" kota_0="428.05"><reka>Sava Dolinka</reka><merilno_mesto>Blejski most</merilno_mesto><ime_kratko>Sava Dol. - Blejski most</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>78</vodostaj><pretok>25.3</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>10.7</temp_vode><prvi_vv_pretok>210</prvi_vv_pretok><drugi_vv_pretok>315</drugi_vv_pretok><tretji_vv_pretok>430</tretji_vv_pretok></postaja>
<postaja sifra="3180" ge_dolzina="14.090418" ge_sirina="46.39384" kota_0="566.07"><reka>Radovna</reka><merilno_mesto>Podhom</merilno_mesto><ime_kratko>Radovna - Podhom</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>126</vodostaj><pretok>5.94</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>7.6</temp_vode><prvi_vv_pretok>70</prvi_vv_pretok><drugi_vv_pretok>110</drugi_vv_pretok><tretji_vv_pretok>148</tretji_vv_pretok></postaja>
<postaja sifra="3200" ge_dolzina="13.891265" ge_sirina="46.278624" kota_0="525.04"><reka>Sava Bohinjka</reka><merilno_mesto>Sveti Janez</merilno_mesto><ime_kratko>Sava Boh. - Sv. Janez</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>129</vodostaj><pretok>5.87</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>17.4</temp_vode><prvi_vv_pretok>110</prvi_vv_pretok><drugi_vv_pretok>128</drugi_vv_pretok><tretji_vv_pretok>173</tretji_vv_pretok></postaja>
<postaja sifra="3250" ge_dolzina="14.146584" ge_sirina="46.342273" kota_0="413.9"><reka>Sava Bohinjka</reka><merilno_mesto>Bodešče</merilno_mesto><ime_kratko>Sava Boh. - Bodešče</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>80</vodostaj><pretok>13.6</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>13.8</temp_vode><prvi_vv_pretok>230</prvi_vv_pretok><drugi_vv_pretok>330</drugi_vv_pretok><tretji_vv_pretok>430</tretji_vv_pretok></postaja>
<postaja sifra="3260" ge_dolzina="13.834237" ge_sirina="46.281305" kota_0="527.43"><reka>Savica</reka><merilno_mesto>Ukanc</merilno_mesto><ime_kratko>Savica - Ukanc</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>63</vodostaj><pretok>3.53</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>6.0</temp_vode><prvi_vv_pretok>94</prvi_vv_pretok><drugi_vv_pretok>120</drugi_vv_pretok><tretji_vv_pretok>146</tretji_vv_pretok></postaja>
<postaja sifra="3280" ge_dolzina="13.869493" ge_sirina="46.279077" kota_0="526"><reka>Bohinjsko jezero</reka><merilno_mesto>Sveti Duh</merilno_mesto><ime_kratko>Bohinjsko j. - Sv. Duh</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>36</vodostaj><vodostaj_znacilni>srednji vodostaj</vodostaj_znacilni><temp_vode>17.1</temp_vode><prvi_vv_vodostaj>300</prvi_vv_vodostaj><drugi_vv_vodostaj>330</drugi_vv_vodostaj><tretji_vv_vodostaj>410</tretji_vv_vodostaj></postaja>
<postaja sifra="3300" ge_dolzina="13.893873" ge_sirina="46.283185" kota_0="527.2"><reka>Mostnica</reka><merilno_mesto>Stara Fužina</merilno_mesto><ime_kratko>Mostnica - Stara Fužina</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>115</vodostaj><pretok>1.34</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>9.5</temp_vode><prvi_vv_pretok>60</prvi_vv_pretok><drugi_vv_pretok>80</drugi_vv_pretok><tretji_vv_pretok>121</tretji_vv_pretok></postaja>
<postaja sifra="3320" ge_dolzina="13.954763" ge_sirina="46.273922" kota_0="504.45"><reka>Bistrica</reka><merilno_mesto>Bohinjska Bistrica</merilno_mesto><ime_kratko>Bistrica - Boh. Bistrica</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>47</vodostaj><pretok>0.85</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>8.1</temp_vode><prvi_vv_pretok>55</prvi_vv_pretok><drugi_vv_pretok>73</drugi_vv_pretok><tretji_vv_pretok>86</tretji_vv_pretok></postaja>
<postaja sifra="3350" ge_dolzina="14.103933" ge_sirina="46.360312" kota_0="475.53"><reka>Blejsko jezero</reka><merilno_mesto>Mlino</merilno_mesto><ime_kratko>Blejsko jezero - Mlino</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>47</vodostaj><vodostaj_znacilni>srednji vodostaj</vodostaj_znacilni><temp_vode>20.4</temp_vode><prvi_vv_vodostaj>100</prvi_vv_vodostaj><drugi_vv_vodostaj>120</drugi_vv_vodostaj><tretji_vv_vodostaj>140</tretji_vv_vodostaj></postaja>
<postaja sifra="3400" ge_dolzina="14.104681" ge_sirina="46.357465" kota_0="467.57"><reka>Jezernica</reka><merilno_mesto>Mlino</merilno_mesto><ime_kratko>Jezernica - Mlino</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>44</vodostaj><pretok>0.33</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>19.9</temp_vode><prvi_vv_pretok>3</prvi_vv_pretok><drugi_vv_pretok>4.2</drugi_vv_pretok><tretji_vv_pretok>5.2</tretji_vv_pretok></postaja>
<postaja sifra="3420" ge_dolzina="14.170064" ge_sirina="46.340376" kota_0="408.18"><reka>Sava</reka><merilno_mesto>Radovljica</merilno_mesto><ime_kratko>Sava - Radovljica</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>72</vodostaj><pretok>39.8</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>12.6</temp_vode><prvi_vv_pretok>500</prvi_vv_pretok><drugi_vv_pretok>650</drugi_vv_pretok><tretji_vv_pretok>750</tretji_vv_pretok></postaja>
<postaja sifra="3465" ge_dolzina="14.324355" ge_sirina="46.256269" kota_0="355.95"><reka>Sava</reka><merilno_mesto>Okroglo</merilno_mesto><ime_kratko>Sava - Okroglo</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>125</vodostaj><pretok>26.3</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>12.9</temp_vode><prvi_vv_pretok>600</prvi_vv_pretok><drugi_vv_pretok>858</drugi_vv_pretok><tretji_vv_pretok>1000</tretji_vv_pretok></postaja>
<postaja sifra="3530" ge_dolzina="14.445225" ge_sirina="46.122844" kota_0="300.29"><reka>Sava</reka><merilno_mesto>Medno</merilno_mesto><ime_kratko>Sava - Medno</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>132</vodostaj><pretok>62.7</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>14.4</temp_vode><prvi_vv_pretok>900</prvi_vv_pretok><drugi_vv_pretok>1000</drugi_vv_pretok><tretji_vv_pretok>1300</tretji_vv_pretok></postaja>
<postaja sifra="3570" ge_dolzina="14.58715" ge_sirina="46.084346" kota_0="268.16"><reka>Sava</reka><merilno_mesto>Šentjakob</merilno_mesto><ime_kratko>Sava - Šentjakob</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>295</vodostaj><pretok>49.8</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.4</temp_vode><prvi_vv_pretok>930</prvi_vv_pretok><drugi_vv_pretok>1170</drugi_vv_pretok><tretji_vv_pretok>1300</tretji_vv_pretok></postaja>
<postaja sifra="3660" ge_dolzina="14.833571" ge_sirina="46.057039" kota_0="230.55"><reka>Sava</reka><merilno_mesto>Litija</merilno_mesto><ime_kratko>Sava - Litija</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>60</vodostaj><pretok>88.6</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>15.7</temp_vode><prvi_vv_pretok>1000</prvi_vv_pretok><drugi_vv_pretok>1514</drugi_vv_pretok><tretji_vv_pretok>1900</tretji_vv_pretok></postaja>
<postaja sifra="3725" ge_dolzina="15.095515" ge_sirina="46.12208" kota_0="193.8"><reka>Sava</reka><merilno_mesto>Hrastnik</merilno_mesto><ime_kratko>Sava - Hrastnik</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>234</vodostaj><pretok>99.1</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>16.5</temp_vode><prvi_vv_pretok>1000</prvi_vv_pretok><drugi_vv_pretok>1600</drugi_vv_pretok><tretji_vv_pretok>2061</tretji_vv_pretok></postaja>
<postaja sifra="3850" ge_dolzina="15.614849" ge_sirina="45.893449" kota_0="137.37"><reka>Sava</reka><merilno_mesto>Čatež</merilno_mesto><ime_kratko>Sava - Čatež</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>136</vodostaj><pretok>133</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>19.8</temp_vode><prvi_vv_pretok>1684</prvi_vv_pretok><drugi_vv_pretok>2580</drugi_vv_pretok><tretji_vv_pretok>3000</tretji_vv_pretok></postaja>
<postaja sifra="3900" ge_dolzina="15.697319" ge_sirina="45.860741" kota_0="129.433"><reka>Sava</reka><merilno_mesto>Jesenice na Dolenjskem</merilno_mesto><ime_kratko>Sava - Jesenice/Dol.</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>79</vodostaj><pretok>108</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>19.9</temp_vode><prvi_vv_pretok>1750</prvi_vv_pretok><drugi_vv_pretok>2699</drugi_vv_pretok><tretji_vv_pretok>3200</tretji_vv_pretok></postaja>
<postaja sifra="4025" ge_dolzina="14.262922" ge_sirina="46.288895" kota_0="375.17"><reka>Lipnica</reka><merilno_mesto>Ovsiše</merilno_mesto><ime_kratko>Lipnica - Ovsiše</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>93</vodostaj><pretok>0.76</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>11.7</temp_vode><prvi_vv_pretok>40</prvi_vv_pretok><drugi_vv_pretok>60</drugi_vv_pretok><tretji_vv_pretok>75</tretji_vv_pretok></postaja>
<postaja sifra="4050" ge_dolzina="14.304307" ge_sirina="46.358188" kota_0="488.76"><reka>Tržiška Bistrica</reka><merilno_mesto>Preska</merilno_mesto><ime_kratko>Tržiška Bis. - Preska</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>73</vodostaj><pretok>3.77</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>8.6</temp_vode><prvi_vv_pretok>60</prvi_vv_pretok><drugi_vv_pretok>95</drugi_vv_pretok><tretji_vv_pretok>130</tretji_vv_pretok></postaja>
<postaja sifra="4120" ge_dolzina="14.503621" ge_sirina="46.306192" kota_0="523"><reka>Kokra</reka><merilno_mesto>Kokra</merilno_mesto><ime_kratko>Kokra - Kokra</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>78</vodostaj><pretok>3.3</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>10.7</temp_vode><prvi_vv_pretok>90</prvi_vv_pretok><drugi_vv_pretok>120</drugi_vv_pretok><tretji_vv_pretok>150</tretji_vv_pretok></postaja>
<postaja sifra="4155" ge_dolzina="14.364289" ge_sirina="46.243396" kota_0="357"><reka>Kokra</reka><merilno_mesto>Kranj</merilno_mesto><ime_kratko>Kokra - Kranj</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>76</vodostaj><pretok>3.75</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>13.7</temp_vode><prvi_vv_pretok>140</prvi_vv_pretok><drugi_vv_pretok>180</drugi_vv_pretok><tretji_vv_pretok>225</tretji_vv_pretok></postaja>
<postaja sifra="4200" ge_dolzina="14.330737" ge_sirina="46.162355" kota_0="329.47"><reka>Sora</reka><merilno_mesto>Suha</merilno_mesto><ime_kratko>Sora - Suha</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>88</vodostaj><pretok>9.43</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>15.0</temp_vode><prvi_vv_pretok>320</prvi_vv_pretok><drugi_vv_pretok>489</drugi_vv_pretok><tretji_vv_pretok>557</tretji_vv_pretok></postaja>
<postaja sifra="4209" ge_dolzina="14.418119" ge_sirina="46.1395" kota_0="306.69"><reka>Sora</reka><merilno_mesto>Medvode</merilno_mesto><ime_kratko>Sora - Medvode</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>125</vodostaj><pretok>10.1</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.1</temp_vode><prvi_vv_pretok>370</prvi_vv_pretok><drugi_vv_pretok>540</drugi_vv_pretok><tretji_vv_pretok>650</tretji_vv_pretok></postaja>
<postaja sifra="4222" ge_dolzina="14.111466" ge_sirina="46.043916" kota_0="474.77"><reka>Poljanska Sora</reka><merilno_mesto>Žiri</merilno_mesto><ime_kratko>Poljanska Sora - Žiri</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>74</vodostaj><pretok>0.52</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>13.2</temp_vode><prvi_vv_pretok>98</prvi_vv_pretok><drugi_vv_pretok>130</drugi_vv_pretok><tretji_vv_pretok>162</tretji_vv_pretok></postaja>
<postaja sifra="4230" ge_dolzina="14.294838" ge_sirina="46.153338" kota_0="343.33"><reka>Poljanska Sora</reka><merilno_mesto>Zminec</merilno_mesto><ime_kratko>Poljanska Sora - Zminec</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>117</vodostaj><pretok>4.57</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>14.8</temp_vode><prvi_vv_pretok>140</prvi_vv_pretok><drugi_vv_pretok>190</drugi_vv_pretok><tretji_vv_pretok>260</tretji_vv_pretok></postaja>
<postaja sifra="4270" ge_dolzina="14.16653" ge_sirina="46.222293" kota_0="447.37"><reka>Selška Sora</reka><merilno_mesto>Železniki</merilno_mesto><ime_kratko>Selška Sora - Železniki</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>127</vodostaj><pretok>2.68</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>12.7</temp_vode><prvi_vv_pretok>82</prvi_vv_pretok><drugi_vv_pretok>120</drugi_vv_pretok><tretji_vv_pretok>220</tretji_vv_pretok></postaja>
<postaja sifra="4298" ge_dolzina="14.289904" ge_sirina="46.172529" kota_0="358.26"><reka>Selška Sora</reka><merilno_mesto>Vešter</merilno_mesto><ime_kratko>Selška Sora - Vešter</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>88</vodostaj><pretok>3.66</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>13.6</temp_vode><prvi_vv_pretok>140</prvi_vv_pretok><drugi_vv_pretok>220</drugi_vv_pretok><tretji_vv_pretok>335</tretji_vv_pretok></postaja>
<postaja sifra="4400" ge_dolzina="14.617865" ge_sirina="46.224423" kota_0="370.65"><reka>Kamniška Bistrica</reka><merilno_mesto>Kamnik</merilno_mesto><ime_kratko>Kamniška B. - Kamnik</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>71</vodostaj><pretok>5.22</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>9.3</temp_vode><prvi_vv_pretok>140</prvi_vv_pretok><drugi_vv_pretok>183</drugi_vv_pretok><tretji_vv_pretok>224</tretji_vv_pretok></postaja>
<postaja sifra="4430" ge_dolzina="14.609034" ge_sirina="46.148006" kota_0="301.25"><reka>Kamniška Bistrica</reka><merilno_mesto>Vir</merilno_mesto><ime_kratko>Kamniška B. - Vir</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>124</vodostaj><pretok>2.67</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>12.0</temp_vode><prvi_vv_pretok>186</prvi_vv_pretok><drugi_vv_pretok>240</drugi_vv_pretok><tretji_vv_pretok>297</tretji_vv_pretok></postaja>
<postaja sifra="4445" ge_dolzina="14.615813" ge_sirina="46.10385" kota_0="274.31"><reka>Kamniška Bistrica</reka><merilno_mesto>Bišče</merilno_mesto><ime_kratko>Kamniška B. - Bišče</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>91</vodostaj><temp_vode>12.9</temp_vode><prvi_vv_pretok>190</prvi_vv_pretok><drugi_vv_pretok>250</drugi_vv_pretok><tretji_vv_pretok>390</tretji_vv_pretok></postaja>
<postaja sifra="4480" ge_dolzina="14.629643" ge_sirina="46.233199" kota_0="379.78"><reka>Nevljica</reka><merilno_mesto>Nevlje</merilno_mesto><ime_kratko>Nevljica - Nevlje</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>90</vodostaj><pretok>0.42</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>14.9</temp_vode><prvi_vv_pretok>31</prvi_vv_pretok><drugi_vv_pretok>44</drugi_vv_pretok><tretji_vv_pretok>68</tretji_vv_pretok></postaja>
<postaja sifra="4515" ge_dolzina="14.621565" ge_sirina="46.144323" kota_0="299.17"><reka>Rača</reka><merilno_mesto>Vir</merilno_mesto><ime_kratko>Rača - Vir</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>45</vodostaj><pretok>0.79</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>16.7</temp_vode><prvi_vv_pretok>42</prvi_vv_pretok><drugi_vv_pretok>61</drugi_vv_pretok><tretji_vv_pretok>80</tretji_vv_pretok></postaja>
<postaja sifra="4520" ge_dolzina="14.614058" ge_sirina="46.142363" kota_0="297.49"><reka>Rača</reka><merilno_mesto>Podrečje</merilno_mesto><ime_kratko>Rača - Podrečje</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>46</vodostaj><pretok>2.28</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.0</temp_vode><prvi_vv_pretok>45</prvi_vv_pretok><drugi_vv_pretok>61</drugi_vv_pretok><tretji_vv_pretok>80</tretji_vv_pretok></postaja>
<postaja sifra="4570" ge_dolzina="14.567374" ge_sirina="46.17405" kota_0="320.27"><reka>Pšata</reka><merilno_mesto>Topole</merilno_mesto><ime_kratko>Pšata - Topole</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>73</vodostaj><pretok>0.86</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>17.4</temp_vode><prvi_vv_pretok>55</prvi_vv_pretok><drugi_vv_pretok>58</drugi_vv_pretok><tretji_vv_pretok>65</tretji_vv_pretok></postaja>
<postaja sifra="4575" ge_dolzina="14.564945" ge_sirina="46.143743" kota_0="301.43"><reka>Pšata</reka><merilno_mesto>Loka</merilno_mesto><ime_kratko>Pšata - Loka</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>17</vodostaj><pretok>0.42</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>17.8</temp_vode><prvi_vv_pretok>14.1</prvi_vv_pretok><drugi_vv_pretok>20</drugi_vv_pretok><tretji_vv_pretok>30</tretji_vv_pretok></postaja>
<postaja sifra="4626" ge_dolzina="15.0011" ge_sirina="46.127879" kota_0="232.21"><reka>Medija</reka><merilno_mesto>Zagorje</merilno_mesto><ime_kratko>Medija - Zagorje</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>23</vodostaj><pretok>0.65</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.6</temp_vode><prvi_vv_pretok>52</prvi_vv_pretok><drugi_vv_pretok>90</drugi_vv_pretok><tretji_vv_pretok>125</tretji_vv_pretok></postaja>
<postaja sifra="4650" ge_dolzina="15.139637" ge_sirina="46.063293" kota_0="256.47"><reka>Sopota</reka><merilno_mesto>Žebnik</merilno_mesto><ime_kratko>Sopota - Žebnik</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>29</vodostaj><pretok>0.44</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>14.4</temp_vode><prvi_vv_pretok>40</prvi_vv_pretok><drugi_vv_pretok>54</drugi_vv_pretok><tretji_vv_pretok>76</tretji_vv_pretok></postaja>
<postaja sifra="4671" ge_dolzina="15.142191" ge_sirina="45.960273" kota_0="228.45"><reka>Mirna</reka><merilno_mesto>Martinja vas</merilno_mesto><ime_kratko>Mirna - Martinja vas</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>90</vodostaj><pretok>0.70</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>17.6</temp_vode><prvi_vv_pretok>55</prvi_vv_pretok><drugi_vv_pretok>90</drugi_vv_pretok><tretji_vv_pretok>130</tretji_vv_pretok></postaja>
<postaja sifra="4695" ge_dolzina="15.235058" ge_sirina="45.988898" kota_0="208.49"><reka>Mirna</reka><merilno_mesto>Jelovec</merilno_mesto><ime_kratko>Mirna - Jelovec</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>53</vodostaj><pretok>0.87</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>17.9</temp_vode><prvi_vv_pretok>75</prvi_vv_pretok><drugi_vv_pretok>125</drugi_vv_pretok><tretji_vv_pretok>155</tretji_vv_pretok></postaja>
<postaja sifra="4706" ge_dolzina="15.302558" ge_sirina="46.026029" kota_0="199.86"><reka>Sevnična</reka><merilno_mesto>Metni Vrh</merilno_mesto><ime_kratko>Sevnična - Metni vrh</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>9</vodostaj><pretok>0.09</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.9</temp_vode><prvi_vv_pretok>19</prvi_vv_pretok><drugi_vv_pretok>35</drugi_vv_pretok><tretji_vv_pretok>54</tretji_vv_pretok></postaja>
<postaja sifra="4710" ge_dolzina="15.704632" ge_sirina="46.223376" kota_0="219.02"><reka>Sotla</reka><merilno_mesto>Rogatec</merilno_mesto><ime_kratko>Sotla - Rogatec</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>38</vodostaj><pretok>0.05</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>17.2</temp_vode><prvi_vv_pretok>22</prvi_vv_pretok><drugi_vv_pretok>44</drugi_vv_pretok><tretji_vv_pretok>77</tretji_vv_pretok></postaja>
<postaja sifra="4750" ge_dolzina="15.709139" ge_sirina="45.921177" kota_0="140.12"><reka>Sotla</reka><merilno_mesto>Rakovec</merilno_mesto><ime_kratko>Sotla - Rakovec</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>19</vodostaj><pretok>1.07</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>20.1</temp_vode><prvi_vv_pretok>85</prvi_vv_pretok><drugi_vv_pretok>115</drugi_vv_pretok><tretji_vv_pretok>130</tretji_vv_pretok></postaja>
<postaja sifra="4770" ge_dolzina="15.606055" ge_sirina="46.173154" kota_0="192.56"><reka>Mestinjščica</reka><merilno_mesto>Sodna vas</merilno_mesto><ime_kratko>Mestinjščica - Sodna vas</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>199</vodostaj><pretok>0.11</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>18.7</temp_vode><prvi_vv_pretok>42</prvi_vv_pretok><drugi_vv_pretok>55</drugi_vv_pretok><tretji_vv_pretok>62</tretji_vv_pretok></postaja>
<postaja sifra="4791" ge_dolzina="15.656321" ge_sirina="46.048581" kota_0="190.36"><reka>Bistrica</reka><merilno_mesto>Zagaj</merilno_mesto><ime_kratko>Bistrica - Zagaj</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>58</vodostaj><pretok>0.38</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>16.3</temp_vode><prvi_vv_pretok>46</prvi_vv_pretok><drugi_vv_pretok>85</drugi_vv_pretok><tretji_vv_pretok>108</tretji_vv_pretok></postaja>
<postaja sifra="4820" ge_dolzina="14.856504" ge_sirina="45.464124" kota_0="219.31"><reka>Kolpa</reka><merilno_mesto>Petrina</merilno_mesto><ime_kratko>Kolpa - Petrina</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>66</vodostaj><pretok>5.17</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>14.1</temp_vode><prvi_vv_pretok>360</prvi_vv_pretok><drugi_vv_pretok>440</drugi_vv_pretok><tretji_vv_pretok>610</tretji_vv_pretok></postaja>
<postaja sifra="4828" ge_dolzina="15.080346" ge_sirina="45.484921" kota_0="180.84"><reka>Kolpa</reka><merilno_mesto>Sodevci</merilno_mesto><ime_kratko>Kolpa - Sodevci</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>73</vodostaj><pretok>12.3</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>16.6</temp_vode><prvi_vv_pretok>400</prvi_vv_pretok><drugi_vv_pretok>600</drugi_vv_pretok><tretji_vv_pretok>750</tretji_vv_pretok></postaja>
<postaja sifra="4860" ge_dolzina="15.327746" ge_sirina="45.635065" kota_0="126.96"><reka>Kolpa</reka><merilno_mesto>Metlika</merilno_mesto><ime_kratko>Kolpa - Metlika</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>28</vodostaj><pretok>17.8</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>19.8</temp_vode><prvi_vv_pretok>650</prvi_vv_pretok><drugi_vv_pretok>850</drugi_vv_pretok><tretji_vv_pretok>1050</tretji_vv_pretok></postaja>
<postaja sifra="4960" ge_dolzina="14.89334" ge_sirina="45.605122" kota_0="453.57"><reka>Rinža</reka><merilno_mesto>Livold</merilno_mesto><ime_kratko>Rinža - Livold</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>79</vodostaj><pretok>0.00</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>14.5</temp_vode><prvi_vv_pretok>25</prvi_vv_pretok><drugi_vv_pretok>38</drugi_vv_pretok><tretji_vv_pretok>52</tretji_vv_pretok></postaja>
<postaja sifra="4969" ge_dolzina="15.249964" ge_sirina="45.613744" kota_0="133.46"><reka>Lahinja</reka><merilno_mesto>Gradac</merilno_mesto><ime_kratko>Lahinja - Gradac</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>108</vodostaj><pretok>0.88</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>19.0</temp_vode><prvi_vv_pretok>55</prvi_vv_pretok><drugi_vv_pretok>95</drugi_vv_pretok><tretji_vv_pretok>110</tretji_vv_pretok></postaja>
<postaja sifra="5030" ge_dolzina="14.304774" ge_sirina="45.966535" kota_0="285.93"><reka>Ljubljanica</reka><merilno_mesto>Vrhnika</merilno_mesto><ime_kratko>Ljubljanica - Vrhnika</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>90</vodostaj><pretok>6.73</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>12.7</temp_vode><prvi_vv_pretok>93</prvi_vv_pretok><drugi_vv_pretok>100</drugi_vv_pretok><tretji_vv_pretok>120</tretji_vv_pretok></postaja>
<postaja sifra="5040" ge_dolzina="14.363757" ge_sirina="45.965718" kota_0="285.54"><reka>Ljubljanica</reka><merilno_mesto>Kamin</merilno_mesto><ime_kratko>Ljubljanica - Kamin</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>64</vodostaj><vodostaj_znacilni>srednji vodostaj</vodostaj_znacilni><temp_vode>13.2</temp_vode><prvi_vv_vodostaj>255</prvi_vv_vodostaj><drugi_vv_vodostaj>350</drugi_vv_vodostaj><tretji_vv_vodostaj>415</tretji_vv_vodostaj></postaja>
<postaja sifra="5078" ge_dolzina="14.54917" ge_sirina="46.055722" kota_0="281.32"><reka>Ljubljanica</reka><merilno_mesto>Moste</merilno_mesto><ime_kratko>Ljubljanica - Moste</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>48</vodostaj><pretok>22.2</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.0</temp_vode><prvi_vv_pretok>150</prvi_vv_pretok><drugi_vv_pretok>273</drugi_vv_pretok><tretji_vv_pretok>358</tretji_vv_pretok></postaja>
<postaja sifra="5240" ge_dolzina="14.313349" ge_sirina="45.95766" kota_0="286.283"><reka>Ljubija</reka><merilno_mesto>Verd</merilno_mesto><ime_kratko>Ljubija - Verd</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>164</vodostaj><pretok>4.67</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>13.4</temp_vode><prvi_vv_pretok>22.5</prvi_vv_pretok><drugi_vv_pretok>23</drugi_vv_pretok><tretji_vv_pretok>24</tretji_vv_pretok></postaja>
<postaja sifra="5270" ge_dolzina="14.344004" ge_sirina="45.950074" kota_0="286.63"><reka>Bistra</reka><merilno_mesto>Bistra</merilno_mesto><ime_kratko>Bistra - Bistra</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>125</vodostaj><pretok>7.58</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>13.2</temp_vode><prvi_vv_pretok>17</prvi_vv_pretok><drugi_vv_pretok>18</drugi_vv_pretok><tretji_vv_pretok>20</tretji_vv_pretok></postaja>
<postaja sifra="5330" ge_dolzina="14.374439" ge_sirina="45.916598" kota_0="295.28"><reka>Borovniščica</reka><merilno_mesto>Borovnica</merilno_mesto><ime_kratko>Borovniščica - Borovnica</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>46</vodostaj><pretok>0.17</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>14.2</temp_vode><prvi_vv_pretok>35</prvi_vv_pretok><drugi_vv_pretok>43</drugi_vv_pretok><tretji_vv_pretok>82</tretji_vv_pretok></postaja>
<postaja sifra="5425" ge_dolzina="14.519376" ge_sirina="45.936503" kota_0="323.181"><reka>Iška</reka><merilno_mesto>Iška vas</merilno_mesto><ime_kratko>Iška - Iška vas</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>156</vodostaj><pretok>0.23</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.4</temp_vode><prvi_vv_pretok>40</prvi_vv_pretok><drugi_vv_pretok>55</drugi_vv_pretok><tretji_vv_pretok>65</tretji_vv_pretok></postaja>
<postaja sifra="5440" ge_dolzina="14.539321" ge_sirina="45.957732" kota_0="287.59"><reka>Ižica</reka><merilno_mesto>Ig</merilno_mesto><ime_kratko>Ižica - Ig</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>203</vodostaj><pretok>0.70</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>12.4</temp_vode><prvi_vv_pretok>12</prvi_vv_pretok><drugi_vv_pretok>21</drugi_vv_pretok><tretji_vv_pretok>26</tretji_vv_pretok></postaja>
<postaja sifra="5479" ge_dolzina="14.451169" ge_sirina="46.045512" kota_0="296.63"><reka>Gradaščica</reka><merilno_mesto>Bokalce</merilno_mesto><ime_kratko>Gradaščica - Bokalce</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>56</vodostaj><pretok>1.87</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.2</temp_vode><prvi_vv_pretok>60</prvi_vv_pretok><drugi_vv_pretok>95</drugi_vv_pretok><tretji_vv_pretok>115</tretji_vv_pretok></postaja>
<postaja sifra="5500" ge_dolzina="14.349653" ge_sirina="46.06259" kota_0="341.12"><reka>Gradaščica</reka><merilno_mesto>Dvor</merilno_mesto><ime_kratko>Gradaščica - Dvor</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>70</vodostaj><pretok>0.73</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>13.5</temp_vode><prvi_vv_pretok>40</prvi_vv_pretok><drugi_vv_pretok>46</drugi_vv_pretok><tretji_vv_pretok>60</tretji_vv_pretok></postaja>
<postaja sifra="5540" ge_dolzina="14.442455" ge_sirina="46.048403" kota_0="298.37"><reka>Šujica</reka><merilno_mesto>Razori</merilno_mesto><ime_kratko>Šujica - Razori</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>29</vodostaj><pretok>0.38</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>16.3</temp_vode><prvi_vv_pretok>25</prvi_vv_pretok><drugi_vv_pretok>50</drugi_vv_pretok><tretji_vv_pretok>61</tretji_vv_pretok></postaja>
<postaja sifra="5670" ge_dolzina="14.409744" ge_sirina="45.728609" kota_0="547.25"><reka>Stržen</reka><merilno_mesto>Gorenje Jezero</merilno_mesto><ime_kratko>Stržen - Gor. Jezero</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>134</vodostaj><vodostaj_znacilni>srednji vodostaj</vodostaj_znacilni><temp_vode>10.6</temp_vode><prvi_vv_vodostaj>320</prvi_vv_vodostaj><drugi_vv_vodostaj>385</drugi_vv_vodostaj><tretji_vv_vodostaj>485</tretji_vv_vodostaj></postaja>
<postaja sifra="5680" ge_dolzina="14.366047" ge_sirina="45.765748" kota_0="545.556"><reka>Stržen</reka><merilno_mesto>Dolenje Jezero</merilno_mesto><ime_kratko>Stržen - Dol. Jezero</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>291</vodostaj><vodostaj_znacilni>srednji vodostaj</vodostaj_znacilni><temp_vode>21.8</temp_vode><prvi_vv_vodostaj>485</prvi_vv_vodostaj><drugi_vv_vodostaj>550</drugi_vv_vodostaj><tretji_vv_vodostaj>650</tretji_vv_vodostaj></postaja>
<postaja sifra="5770" ge_dolzina="14.369442" ge_sirina="45.794177" kota_0="559.44"><reka>Cerkniščica</reka><merilno_mesto>Cerknica</merilno_mesto><ime_kratko>Cerkniščica - Cerknica</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>193</vodostaj><pretok>0.46</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>14.4</temp_vode><prvi_vv_pretok>16</prvi_vv_pretok><drugi_vv_pretok>30</drugi_vv_pretok><tretji_vv_pretok>46</tretji_vv_pretok></postaja>
<postaja sifra="5800" ge_dolzina="14.191382" ge_sirina="45.731052" kota_0="519.75"><reka>Pivka</reka><merilno_mesto>Prestranek</merilno_mesto><ime_kratko>Pivka - Prestranek</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>107</vodostaj><pretok>0.39</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>13.4</temp_vode><prvi_vv_pretok>17</prvi_vv_pretok><drugi_vv_pretok>38</drugi_vv_pretok><tretji_vv_pretok>55</tretji_vv_pretok></postaja>
<postaja sifra="5820" ge_dolzina="14.207935" ge_sirina="45.782715" kota_0="510.93"><reka>Pivka</reka><merilno_mesto>Postojnska jama</merilno_mesto><ime_kratko>Pivka - Postojnska jama</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>102</vodostaj><vodostaj_znacilni>srednji vodostaj</vodostaj_znacilni><temp_vode>16.3</temp_vode><prvi_vv_vodostaj>600</prvi_vv_vodostaj><drugi_vv_vodostaj>700</drugi_vv_vodostaj><tretji_vv_vodostaj>800</tretji_vv_vodostaj></postaja>
<postaja sifra="5840" ge_dolzina="14.184997" ge_sirina="45.780421" kota_0="516.04"><reka>Nanoščica</reka><merilno_mesto>Mali Otok</merilno_mesto><ime_kratko>Nanoščica - Mali otok</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>92</vodostaj><pretok>0.22</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>17.3</temp_vode><prvi_vv_pretok>18.3</prvi_vv_pretok><drugi_vv_pretok>20.8</drugi_vv_pretok><tretji_vv_pretok>22.6</tretji_vv_pretok></postaja>
<postaja sifra="5880" ge_dolzina="14.268645" ge_sirina="45.828798" kota_0="444.88"><reka>Unica</reka><merilno_mesto>Hasberg</merilno_mesto><ime_kratko>Unica - Hasberg</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>59</vodostaj><pretok>12.4</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>14.2</temp_vode><prvi_vv_pretok>84</prvi_vv_pretok><drugi_vv_pretok>87.6</drugi_vv_pretok><tretji_vv_pretok>91</tretji_vv_pretok></postaja>
<postaja sifra="5910" ge_dolzina="14.259811" ge_sirina="45.823243" kota_0="444.86"><reka>Malenščica</reka><merilno_mesto>Malni</merilno_mesto><ime_kratko>Malenščica - Malni</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>118</vodostaj><pretok>5.82</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>14.7</temp_vode><prvi_vv_pretok>9.2</prvi_vv_pretok><drugi_vv_pretok>9.5</drugi_vv_pretok><tretji_vv_pretok>9.9</tretji_vv_pretok></postaja>
<postaja sifra="5940" ge_dolzina="14.20937" ge_sirina="45.911265" kota_0="481.34"><reka>Logaščica</reka><merilno_mesto>Logatec</merilno_mesto><ime_kratko>Logaščica - Logatec</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>126</vodostaj><pretok>0.14</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.5</temp_vode><prvi_vv_pretok>8.2</prvi_vv_pretok><drugi_vv_pretok>10.9</drugi_vv_pretok><tretji_vv_pretok>12.7</tretji_vv_pretok></postaja>
<postaja sifra="6020" ge_dolzina="14.697937" ge_sirina="46.420012" kota_0="635.84"><reka>Savinja</reka><merilno_mesto>Solčava</merilno_mesto><ime_kratko>Savinja - Solčava</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>94</vodostaj><temp_vode>8.6</temp_vode><prvi_vv_pretok>42</prvi_vv_pretok><drugi_vv_pretok>50</drugi_vv_pretok><tretji_vv_pretok>82</tretji_vv_pretok></postaja>
<postaja sifra="6060" ge_dolzina="14.957271" ge_sirina="46.321604" kota_0="336.97"><reka>Savinja</reka><merilno_mesto>Nazarje</merilno_mesto><ime_kratko>Savinja - Nazarje</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>48</vodostaj><pretok>6.90</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>14.0</temp_vode><prvi_vv_pretok>310</prvi_vv_pretok><drugi_vv_pretok>420</drugi_vv_pretok><tretji_vv_pretok>530</tretji_vv_pretok></postaja>
<postaja sifra="6068" ge_dolzina="15.009222" ge_sirina="46.326561" kota_0="313.444"><reka>Savinja</reka><merilno_mesto>Letuš</merilno_mesto><ime_kratko>Savinja - Letuš</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>104</vodostaj><pretok>8.30</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>14.9</temp_vode><prvi_vv_pretok>360</prvi_vv_pretok><drugi_vv_pretok>450</drugi_vv_pretok><tretji_vv_pretok>580</tretji_vv_pretok></postaja>
<postaja sifra="6120" ge_dolzina="15.225234" ge_sirina="46.233523" kota_0="238.164"><reka>Savinja</reka><merilno_mesto>Medlog</merilno_mesto><ime_kratko>Savinja - Medlog</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>134</vodostaj><vodostaj_znacilni>nizek vodostaj</vodostaj_znacilni><temp_vode>17.5</temp_vode><prvi_vv_vodostaj>385</prvi_vv_vodostaj><drugi_vv_vodostaj>463</drugi_vv_vodostaj><tretji_vv_vodostaj>532</tretji_vv_vodostaj></postaja>
<postaja sifra="6140" ge_dolzina="15.260021" ge_sirina="46.228067" kota_0="230.31"><reka>Savinja</reka><merilno_mesto>Celje</merilno_mesto><ime_kratko>Savinja - Celje brv</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>101</vodostaj><pretok>10.6</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>18.0</temp_vode><prvi_vv_pretok>480</prvi_vv_pretok><drugi_vv_pretok>812</drugi_vv_pretok><tretji_vv_pretok>1034</tretji_vv_pretok></postaja>
<postaja sifra="6200" ge_dolzina="15.238377" ge_sirina="46.154266" kota_0="215.025"><reka>Savinja</reka><merilno_mesto>Laško</merilno_mesto><ime_kratko>Savinja - Laško</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>85</vodostaj><pretok>12.8</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>18.8</temp_vode><prvi_vv_pretok>470</prvi_vv_pretok><drugi_vv_pretok>854</drugi_vv_pretok><tretji_vv_pretok>1193</tretji_vv_pretok></postaja>
<postaja sifra="6210" ge_dolzina="15.197162" ge_sirina="46.092318" kota_0="189.45"><reka>Savinja</reka><merilno_mesto>Veliko Širje</merilno_mesto><ime_kratko>Savinja - Vel. Širje</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>170</vodostaj><pretok>11.1</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>21.1</temp_vode><prvi_vv_pretok>560</prvi_vv_pretok><drugi_vv_pretok>907</drugi_vv_pretok><tretji_vv_pretok>1284</tretji_vv_pretok></postaja>
<postaja sifra="6220" ge_dolzina="14.750682" ge_sirina="46.354607" kota_0="509.79"><reka>Lučnica</reka><merilno_mesto>Luče</merilno_mesto><ime_kratko>Lučnica - Luče</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>86</vodostaj><pretok>0.85</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>10.7</temp_vode><prvi_vv_pretok>60</prvi_vv_pretok><drugi_vv_pretok>72</drugi_vv_pretok><tretji_vv_pretok>90</tretji_vv_pretok></postaja>
<postaja sifra="6240" ge_dolzina="14.904955" ge_sirina="46.284995" kota_0="365.55"><reka>Dreta</reka><merilno_mesto>Kraše</merilno_mesto><ime_kratko>Dreta - Kraše</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>76</vodostaj><pretok>1.49</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>12.5</temp_vode><prvi_vv_pretok>110</prvi_vv_pretok><drugi_vv_pretok>150</drugi_vv_pretok><tretji_vv_pretok>225</tretji_vv_pretok></postaja>
<postaja sifra="6280" ge_dolzina="15.123149" ge_sirina="46.362128" kota_0="389.09"><reka>Paka</reka><merilno_mesto>Velenje</merilno_mesto><ime_kratko>Paka - Velenje</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>101</vodostaj><pretok>0.44</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>14.2</temp_vode><prvi_vv_pretok>21</prvi_vv_pretok><drugi_vv_pretok>35</drugi_vv_pretok><tretji_vv_pretok>60</tretji_vv_pretok></postaja>
<postaja sifra="6300" ge_dolzina="15.053198" ge_sirina="46.376093" kota_0="353.03"><reka>Paka</reka><merilno_mesto>Šoštanj</merilno_mesto><ime_kratko>Paka - Šoštanj</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>159</vodostaj><pretok>0.71</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>19.0</temp_vode><prvi_vv_pretok>45</prvi_vv_pretok><drugi_vv_pretok>81</drugi_vv_pretok><tretji_vv_pretok>90</tretji_vv_pretok></postaja>
<postaja sifra="6340" ge_dolzina="15.043145" ge_sirina="46.321424" kota_0="305.01"><reka>Paka</reka><merilno_mesto>Rečica</merilno_mesto><ime_kratko>Paka - Rečica</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>84</vodostaj><pretok>1.72</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>18.7</temp_vode><prvi_vv_pretok>83</prvi_vv_pretok><drugi_vv_pretok>167</drugi_vv_pretok><tretji_vv_pretok>210</tretji_vv_pretok></postaja>
<postaja sifra="6415" ge_dolzina="15.082749" ge_sirina="46.393405" kota_0="385.6"><reka>Velunja</reka><merilno_mesto>Gaberke</merilno_mesto><ime_kratko>Velunja - Gaberke</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>128</vodostaj><pretok>0.19</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.2</temp_vode><prvi_vv_pretok>16</prvi_vv_pretok><drugi_vv_pretok>27</drugi_vv_pretok><tretji_vv_pretok>45</tretji_vv_pretok></postaja>
<postaja sifra="6550" ge_dolzina="15.09769" ge_sirina="46.239534" kota_0="267.87"><reka>Bolska</reka><merilno_mesto>Dolenja vas</merilno_mesto><ime_kratko>Bolska - Dolenja vas</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>90</vodostaj><pretok>0.69</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.7</temp_vode><prvi_vv_pretok>70</prvi_vv_pretok><drugi_vv_pretok>120</drugi_vv_pretok><tretji_vv_pretok>170</tretji_vv_pretok></postaja>
<postaja sifra="6630" ge_dolzina="15.224967" ge_sirina="46.244187" kota_0="240.951"><reka>Ložnica</reka><merilno_mesto>Levec</merilno_mesto><ime_kratko>Ložnica - Levec</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>48</vodostaj><pretok>0.19</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>18.8</temp_vode><prvi_vv_pretok>50</prvi_vv_pretok><drugi_vv_pretok>80</drugi_vv_pretok><tretji_vv_pretok>120</tretji_vv_pretok></postaja>
<postaja sifra="6691" ge_dolzina="15.427647" ge_sirina="46.200101" kota_0="263.27"><reka>Voglajna</reka><merilno_mesto>Črnolica</merilno_mesto><ime_kratko>Voglajna - Črnolica</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>44</vodostaj><pretok>0.07</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>16.9</temp_vode><prvi_vv_pretok>44</prvi_vv_pretok><drugi_vv_pretok>56</drugi_vv_pretok><tretji_vv_pretok>70</tretji_vv_pretok></postaja>
<postaja sifra="6720" ge_dolzina="15.288053" ge_sirina="46.232338" kota_0="234.23"><reka>Voglajna</reka><merilno_mesto>Celje</merilno_mesto><ime_kratko>Voglajna - Celje</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>92</vodostaj><pretok>0.26</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>18.8</temp_vode><prvi_vv_pretok>66</prvi_vv_pretok><drugi_vv_pretok>97</drugi_vv_pretok><tretji_vv_pretok>132</tretji_vv_pretok></postaja>
<postaja sifra="6770" ge_dolzina="15.284094" ge_sirina="46.314484" kota_0="285.75"><reka>Hudinja</reka><merilno_mesto>Polže</merilno_mesto><ime_kratko>Hudinja - Polže</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>31</vodostaj><pretok>0.35</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.1</temp_vode><prvi_vv_pretok>30</prvi_vv_pretok><drugi_vv_pretok>42</drugi_vv_pretok><tretji_vv_pretok>93</tretji_vv_pretok></postaja>
<postaja sifra="6790" ge_dolzina="15.291532" ge_sirina="46.265002" kota_0="242.23"><reka>Hudinja</reka><merilno_mesto>Škofja vas</merilno_mesto><ime_kratko>Hudinja - Škofja vas</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>34</vodostaj><pretok>0.80</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>17.2</temp_vode><prvi_vv_pretok>59</prvi_vv_pretok><drugi_vv_pretok>84</drugi_vv_pretok><tretji_vv_pretok>132</tretji_vv_pretok></postaja>
<postaja sifra="6835" ge_dolzina="15.238472" ge_sirina="46.107249" kota_0="211.84"><reka>Gračnica</reka><merilno_mesto>Vodiško</merilno_mesto><ime_kratko>Gračnica - Vodiško</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>128</vodostaj><pretok>0.26</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>16.0</temp_vode><prvi_vv_pretok>48</prvi_vv_pretok><drugi_vv_pretok>84</drugi_vv_pretok><tretji_vv_pretok>108</tretji_vv_pretok></postaja>
<postaja sifra="7029" ge_dolzina="14.789738" ge_sirina="45.877029" kota_0="258.49"><reka>Krka</reka><merilno_mesto>Podbukovje</merilno_mesto><ime_kratko>Krka - Podbukovje</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>30</vodostaj><pretok>1.93</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>12.3</temp_vode><prvi_vv_pretok>40</prvi_vv_pretok><drugi_vv_pretok>75</drugi_vv_pretok><tretji_vv_pretok>110</tretji_vv_pretok></postaja>
<postaja sifra="7060" ge_dolzina="15.023973" ge_sirina="45.779671" kota_0="167.59"><reka>Krka</reka><merilno_mesto>Soteska</merilno_mesto><ime_kratko>Krka - Soteska</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>128</vodostaj><pretok>9.09</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>13.9</temp_vode><prvi_vv_pretok>120</prvi_vv_pretok><drugi_vv_pretok>160</drugi_vv_pretok><tretji_vv_pretok>220</tretji_vv_pretok></postaja>
<postaja sifra="7110" ge_dolzina="15.290363" ge_sirina="45.867773" kota_0="148.67"><reka>Krka</reka><merilno_mesto>Gorenja Gomila</merilno_mesto><ime_kratko>Krka - G. Gomila</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>61</vodostaj><pretok>18.3</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>18.1</temp_vode><prvi_vv_pretok>180</prvi_vv_pretok><drugi_vv_pretok>250</drugi_vv_pretok><tretji_vv_pretok>320</tretji_vv_pretok></postaja>
<postaja sifra="7160" ge_dolzina="15.460184" ge_sirina="45.865092" kota_0="146.27"><reka>Krka</reka><merilno_mesto>Podbočje</merilno_mesto><ime_kratko>Krka - Podbočje</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>77</vodostaj><pretok>19.6</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>19.1</temp_vode><prvi_vv_pretok>200</prvi_vv_pretok><drugi_vv_pretok>300</drugi_vv_pretok><tretji_vv_pretok>380</tretji_vv_pretok></postaja>
<postaja sifra="7200" ge_dolzina="14.677928" ge_sirina="45.940498" kota_0="323.94"><reka>Grosupeljščica</reka><merilno_mesto>Mlačevo</merilno_mesto><ime_kratko>Grosupeljščica - Mlačevo</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>96</vodostaj><pretok>0.12</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.5</temp_vode><prvi_vv_pretok>4.8</prvi_vv_pretok><drugi_vv_pretok>7</drugi_vv_pretok><tretji_vv_pretok>8</tretji_vv_pretok></postaja>
<postaja sifra="7220" ge_dolzina="14.633443" ge_sirina="45.852076" kota_0="473.29"><reka>Rašica</reka><merilno_mesto>Rašica</merilno_mesto><ime_kratko>Rašica - Rašica</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>73</vodostaj><pretok>0.57</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.6</temp_vode><prvi_vv_pretok>31</prvi_vv_pretok><drugi_vv_pretok>39</drugi_vv_pretok><tretji_vv_pretok>47</tretji_vv_pretok></postaja>
<postaja sifra="7235" ge_dolzina="14.805146" ge_sirina="45.936421" kota_0="321.98"><reka>Višnjica</reka><merilno_mesto>Ivančna Gorica</merilno_mesto><ime_kratko>Višnjica - Ivančna Gorica</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>68</vodostaj><pretok>0.18</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>16.1</temp_vode><prvi_vv_pretok>9</prvi_vv_pretok><drugi_vv_pretok>20</drugi_vv_pretok><tretji_vv_pretok>34</tretji_vv_pretok></postaja>
<postaja sifra="7272" ge_dolzina="15.040254" ge_sirina="45.759878" kota_0="167.88"><reka>Radešca</reka><merilno_mesto>Meniška vas</merilno_mesto><ime_kratko>Radešca - Meniška vas</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>106</vodostaj><pretok>2.91</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>10.5</temp_vode><prvi_vv_pretok>34</prvi_vv_pretok><drugi_vv_pretok>47</drugi_vv_pretok><tretji_vv_pretok>55</tretji_vv_pretok></postaja>
<postaja sifra="7308" ge_dolzina="14.994985" ge_sirina="45.907917" kota_0="269.11"><reka>Temenica</reka><merilno_mesto>Rožni Vrh</merilno_mesto><ime_kratko>Temenica-Rožni Vrh</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>118</vodostaj><pretok>0.11</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>19.5</temp_vode><prvi_vv_pretok>6.9</prvi_vv_pretok><drugi_vv_pretok>9.3</drugi_vv_pretok><tretji_vv_pretok>15</tretji_vv_pretok></postaja>
<postaja sifra="7340" ge_dolzina="15.113508" ge_sirina="45.815001" kota_0="163.819"><reka>Prečna</reka><merilno_mesto>Prečna</merilno_mesto><ime_kratko>Prečna - Prečna</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>89</vodostaj><pretok>1.54</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>12.5</temp_vode><prvi_vv_pretok>19.5</prvi_vv_pretok><drugi_vv_pretok>38</drugi_vv_pretok><tretji_vv_pretok>45</tretji_vv_pretok></postaja>
<postaja sifra="7380" ge_dolzina="15.296682" ge_sirina="45.907802" kota_0="159.6"><reka>Radulja</reka><merilno_mesto>Škocjan</merilno_mesto><ime_kratko>Radulja - Škocjan</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>67</vodostaj><pretok>0.40</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>17.9</temp_vode><prvi_vv_pretok>30</prvi_vv_pretok><drugi_vv_pretok>37</drugi_vv_pretok><tretji_vv_pretok>45</tretji_vv_pretok></postaja>
<postaja sifra="7440" ge_dolzina="14.642408" ge_sirina="45.763267" kota_0="532.04"><reka>Bistrica</reka><merilno_mesto>Sodražica</merilno_mesto><ime_kratko>Bistrica - Sodražica</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>94</vodostaj><pretok>0.21</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>14.7</temp_vode><prvi_vv_pretok>16</prvi_vv_pretok><drugi_vv_pretok>21</drugi_vv_pretok><tretji_vv_pretok>28</tretji_vv_pretok></postaja>
<postaja sifra="7488" ge_dolzina="14.742466" ge_sirina="45.712602" kota_0="482.06"><reka>Ribnica</reka><merilno_mesto>Prigorica</merilno_mesto><ime_kratko>Ribnica - Prigorica</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>54</vodostaj><pretok>0.51</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>12.2</temp_vode><prvi_vv_pretok>14</prvi_vv_pretok><drugi_vv_pretok>16</drugi_vv_pretok><tretji_vv_pretok>18</tretji_vv_pretok></postaja>
<postaja sifra="7498" ge_dolzina="14.749649" ge_sirina="45.694853" kota_0="477.3"><reka>Rakitnica</reka><merilno_mesto>Blate</merilno_mesto><ime_kratko>Rakitnica - Blate</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>118</vodostaj><vodostaj_znacilni>nizek vodostaj</vodostaj_znacilni><temp_vode>8.2</temp_vode><prvi_vv_vodostaj>370</prvi_vv_vodostaj><drugi_vv_vodostaj>430</drugi_vv_vodostaj><tretji_vv_vodostaj>520</tretji_vv_vodostaj></postaja>
<postaja sifra="8031" ge_dolzina="13.603292" ge_sirina="46.337332" kota_0="401.57"><reka>Soča</reka><merilno_mesto>Kršovec</merilno_mesto><ime_kratko>Soča - Kršovec</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>111</vodostaj><pretok>10.4</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>8.6</temp_vode><prvi_vv_pretok>140</prvi_vv_pretok><drugi_vv_pretok>240</drugi_vv_pretok><tretji_vv_pretok>320</tretji_vv_pretok></postaja>
<postaja sifra="8060" ge_dolzina="13.499251" ge_sirina="46.315182" kota_0="340.3"><reka>Soča</reka><merilno_mesto>Log Čezsoški</merilno_mesto><ime_kratko>Soča - Log Čezsoški</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>113</vodostaj><pretok>14.0</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>9.0</temp_vode><prvi_vv_pretok>335</prvi_vv_pretok><drugi_vv_pretok>450</drugi_vv_pretok><tretji_vv_pretok>500</tretji_vv_pretok></postaja>
<postaja sifra="8080" ge_dolzina="13.591029" ge_sirina="46.247711" kota_0="194.68"><reka>Soča</reka><merilno_mesto>Kobarid</merilno_mesto><ime_kratko>Soča - Kobarid</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>133</vodostaj><pretok>28.2</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>10.5</temp_vode><prvi_vv_pretok>400</prvi_vv_pretok><drugi_vv_pretok>500</drugi_vv_pretok><tretji_vv_pretok>650</tretji_vv_pretok></postaja>
<postaja sifra="8180" ge_dolzina="13.659889" ge_sirina="45.981894" kota_0="53.77"><reka>Soča</reka><merilno_mesto>Solkan</merilno_mesto><ime_kratko>Soča - Solkan</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>308</vodostaj><pretok>133</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>12.9</temp_vode><prvi_vv_pretok>1500</prvi_vv_pretok><drugi_vv_pretok>1700</drugi_vv_pretok><tretji_vv_pretok>1900</tretji_vv_pretok></postaja>
<postaja sifra="8242" ge_dolzina="13.579986" ge_sirina="46.341814" kota_0="404.7"><reka>Koritnica</reka><merilno_mesto>Kal-Koritnica</merilno_mesto><ime_kratko>Koritnica - Kal-Korit.</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>122</vodostaj><prvi_vv_pretok>50</prvi_vv_pretok><drugi_vv_pretok>126</drugi_vv_pretok><tretji_vv_pretok>220</tretji_vv_pretok></postaja>
<postaja sifra="8270" ge_dolzina="13.482378" ge_sirina="46.310145" kota_0="341.56"><reka>Učja</reka><merilno_mesto>Žaga</merilno_mesto><ime_kratko>Učja - Žaga</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>115</vodostaj><pretok>1.58</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><prvi_vv_pretok>105</prvi_vv_pretok><drugi_vv_pretok>175</drugi_vv_pretok><tretji_vv_pretok>235</tretji_vv_pretok></postaja>
<postaja sifra="8332" ge_dolzina="13.745198" ge_sirina="46.184368" kota_0="161.43"><reka>Tolminka</reka><merilno_mesto>Tolmin</merilno_mesto><ime_kratko>Tolminka - Tolmin</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>96</vodostaj><pretok>3.28</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>9.5</temp_vode><prvi_vv_pretok>70</prvi_vv_pretok><drugi_vv_pretok>135</drugi_vv_pretok><tretji_vv_pretok>165</tretji_vv_pretok></postaja>
<postaja sifra="8350" ge_dolzina="14.035385" ge_sirina="45.987075" kota_0="327.05"><reka>Idrijca</reka><merilno_mesto>Podroteja</merilno_mesto><ime_kratko>Idrijca - Podroteja</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>84</vodostaj><pretok>2.95</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>10.3</temp_vode><prvi_vv_pretok>200</prvi_vv_pretok><drugi_vv_pretok>320</drugi_vv_pretok><tretji_vv_pretok>370</tretji_vv_pretok></postaja>
<postaja sifra="8351" ge_dolzina="14.034904" ge_sirina="45.987251" kota_0="339.51"><reka>Idrijca-kanal</reka><merilno_mesto>Podroteja</merilno_mesto><ime_kratko>Idrijca Kanal - Podroteja</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>7</vodostaj><pretok>0.00</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>15.2</temp_vode><prvi_vv_pretok>2.5</prvi_vv_pretok><drugi_vv_pretok>2.8</drugi_vv_pretok><tretji_vv_pretok>3</tretji_vv_pretok></postaja>
<postaja sifra="8450" ge_dolzina="13.798362" ge_sirina="46.128681" kota_0="160.86"><reka>Idrijca</reka><merilno_mesto>Hotešk</merilno_mesto><ime_kratko>Idrijca - Hotešk</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>47</vodostaj><pretok>7.57</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>12.1</temp_vode><prvi_vv_pretok>450</prvi_vv_pretok><drugi_vv_pretok>760</drugi_vv_pretok><tretji_vv_pretok>950</tretji_vv_pretok></postaja>
<postaja sifra="8454" ge_dolzina="13.983514" ge_sirina="46.121695" kota_0="295.96"><reka>Cerknica</reka><merilno_mesto>Cerkno</merilno_mesto><ime_kratko>Cerknica - Cerkno</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>141</vodostaj><pretok>0.88</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>13.4</temp_vode><prvi_vv_pretok>25</prvi_vv_pretok><drugi_vv_pretok>41</drugi_vv_pretok><tretji_vv_pretok>65</tretji_vv_pretok></postaja>
<postaja sifra="8478" ge_dolzina="13.836737" ge_sirina="46.094226" kota_0="187.04"><reka>Trebuša</reka><merilno_mesto>Dolenja Trebuša</merilno_mesto><ime_kratko>Trebuša - Dol. Trebuša</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>59</vodostaj><pretok>1.14</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>13.8</temp_vode><prvi_vv_pretok>65</prvi_vv_pretok><drugi_vv_pretok>110</drugi_vv_pretok><tretji_vv_pretok>135</tretji_vv_pretok></postaja>
<postaja sifra="8500" ge_dolzina="13.780198" ge_sirina="46.155943" kota_0="164.04"><reka>Bača</reka><merilno_mesto>Bača pri Modreju</merilno_mesto><ime_kratko>Bača - Bača pri Modreju</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>85</vodostaj><pretok>4.17</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>12.9</temp_vode><prvi_vv_pretok>160</prvi_vv_pretok><drugi_vv_pretok>190</drugi_vv_pretok><tretji_vv_pretok>220</tretji_vv_pretok></postaja>
<postaja sifra="8545" ge_dolzina="13.638662" ge_sirina="45.953171" kota_0="84.08"><reka>Koren</reka><merilno_mesto>Nova Gorica</merilno_mesto><ime_kratko>Koren - Nova Gorica</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>61</vodostaj><pretok>0.03</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>17.9</temp_vode><prvi_vv_pretok>9.4</prvi_vv_pretok><drugi_vv_pretok>10.8</drugi_vv_pretok><tretji_vv_pretok>12.3</tretji_vv_pretok></postaja>
<postaja sifra="8561" ge_dolzina="13.965745" ge_sirina="45.84244" kota_0="96.09"><reka>Vipava</reka><merilno_mesto>Vipava</merilno_mesto><ime_kratko>Vipava - Vipava</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>21</vodostaj><pretok>1.58</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>9.8</temp_vode><prvi_vv_pretok>50</prvi_vv_pretok><drugi_vv_pretok>62</drugi_vv_pretok><tretji_vv_pretok>80</tretji_vv_pretok></postaja>
<postaja sifra="8565" ge_dolzina="13.905993" ge_sirina="45.866541" kota_0="81.36"><reka>Vipava</reka><merilno_mesto>Dolenje</merilno_mesto><ime_kratko>Vipava - Dolenje</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>62</vodostaj><pretok>3.82</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>11.2</temp_vode><prvi_vv_pretok>130</prvi_vv_pretok><drugi_vv_pretok>190</drugi_vv_pretok><tretji_vv_pretok>230</tretji_vv_pretok></postaja>
<postaja sifra="8591" ge_dolzina="13.74809" ge_sirina="45.888497" kota_0="53.9"><reka>Vipava</reka><merilno_mesto>Zalošče</merilno_mesto><ime_kratko>Vipava - Zalošče</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>71</vodostaj><pretok>4.44</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>14.5</temp_vode><prvi_vv_pretok>150</prvi_vv_pretok><drugi_vv_pretok>225</drugi_vv_pretok><tretji_vv_pretok>300</tretji_vv_pretok></postaja>
<postaja sifra="8601" ge_dolzina="13.612571" ge_sirina="45.8944" kota_0="35.93"><reka>Vipava</reka><merilno_mesto>Miren</merilno_mesto><ime_kratko>Vipava - Miren</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>95</vodostaj><pretok>5.29</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>17.6</temp_vode><prvi_vv_pretok>190</prvi_vv_pretok><drugi_vv_pretok>260</drugi_vv_pretok><tretji_vv_pretok>400</tretji_vv_pretok></postaja>
<postaja sifra="8610" ge_dolzina="13.978341" ge_sirina="45.799358" kota_0="161.26"><reka>Močilnik</reka><merilno_mesto>Podnanos</merilno_mesto><ime_kratko>Močilnik - Podnanos</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>24</vodostaj><pretok>0.13</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>16.4</temp_vode><prvi_vv_pretok>20</prvi_vv_pretok><drugi_vv_pretok>23</drugi_vv_pretok><tretji_vv_pretok>40</tretji_vv_pretok></postaja>
<postaja sifra="8630" ge_dolzina="13.909731" ge_sirina="45.89405" kota_0="107.14"><reka>Hubelj</reka><merilno_mesto>Ajdovščina</merilno_mesto><ime_kratko>Hubelj - Ajdovščina</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>47</vodostaj><pretok>1.32</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>9.2</temp_vode><prvi_vv_pretok>30</prvi_vv_pretok><drugi_vv_pretok>40</drugi_vv_pretok><tretji_vv_pretok>50</tretji_vv_pretok></postaja>
<postaja sifra="8640" ge_dolzina="13.808814" ge_sirina="45.851539" kota_0="81.06"><reka>Branica</reka><merilno_mesto>Branik</merilno_mesto><ime_kratko>Branica - Branik</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>154</vodostaj><pretok>0.06</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>19.0</temp_vode><prvi_vv_pretok>45</prvi_vv_pretok><drugi_vv_pretok>70</drugi_vv_pretok><tretji_vv_pretok>90</tretji_vv_pretok></postaja>
<postaja sifra="8660" ge_dolzina="13.68791" ge_sirina="45.899997" kota_0="44.94"><reka>Lijak</reka><merilno_mesto>Volčja Draga</merilno_mesto><ime_kratko>Lijak - Volčja Draga</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>110</vodostaj><pretok>0.37</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>18.2</temp_vode><prvi_vv_pretok>52</prvi_vv_pretok><drugi_vv_pretok>85</drugi_vv_pretok><tretji_vv_pretok>100</tretji_vv_pretok></postaja>
<postaja sifra="8670" ge_dolzina="13.726277" ge_sirina="45.905227" kota_0="66.27"><reka>Vogršček</reka><merilno_mesto>Bezovljak</merilno_mesto><ime_kratko>Vogršček - Bezovljak</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj></vodostaj><pretok></pretok><pretok_znacilni></pretok_znacilni><temp_vode></temp_vode><prvi_vv_pretok>11</prvi_vv_pretok><drugi_vv_pretok>22</drugi_vv_pretok><tretji_vv_pretok>25</tretji_vv_pretok></postaja>
<postaja sifra="8680" ge_dolzina="13.49456" ge_sirina="46.000046" kota_0="72.04"><reka>Reka</reka><merilno_mesto>Neblo</merilno_mesto><ime_kratko>Reka - Neblo</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>116</vodostaj><pretok>0.22</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>18.2</temp_vode><prvi_vv_pretok>35</prvi_vv_pretok><drugi_vv_pretok>75</drugi_vv_pretok><tretji_vv_pretok>105</tretji_vv_pretok></postaja>
<postaja sifra="8690" ge_dolzina="13.502009" ge_sirina="46.053669" kota_0="99.91"><reka>Idrija</reka><merilno_mesto>Golo Brdo</merilno_mesto><ime_kratko>Idrija - Golo Brdo</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj></vodostaj><pretok></pretok><pretok_znacilni></pretok_znacilni><temp_vode></temp_vode><prvi_vv_pretok>85</prvi_vv_pretok><drugi_vv_pretok>110</drugi_vv_pretok><tretji_vv_pretok>135</tretji_vv_pretok></postaja>
<postaja sifra="8700" ge_dolzina="13.509068" ge_sirina="46.003125" kota_0="80.02"><reka>Kožbanjšček</reka><merilno_mesto>Neblo</merilno_mesto><ime_kratko>Kožbanjšček - Neblo</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>134</vodostaj><pretok>0.06</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>17.5</temp_vode><prvi_vv_pretok>29</prvi_vv_pretok><drugi_vv_pretok>43</drugi_vv_pretok><tretji_vv_pretok>62</tretji_vv_pretok></postaja>
<postaja sifra="8710" ge_dolzina="13.505872" ge_sirina="46.248897" kota_0="245.98"><reka>Nadiža</reka><merilno_mesto>Potoki</merilno_mesto><ime_kratko>Nadiža - Potoki</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>137</vodostaj><pretok>1.70</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>14.1</temp_vode><prvi_vv_pretok>100</prvi_vv_pretok><drugi_vv_pretok>152</drugi_vv_pretok><tretji_vv_pretok>230</tretji_vv_pretok></postaja>
<postaja sifra="9015" ge_dolzina="14.327693" ge_sirina="45.525331" kota_0="422.45"><reka>Reka</reka><merilno_mesto>Trpčane</merilno_mesto><ime_kratko>Reka - Trpčane</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>97</vodostaj><temp_vode>17.3</temp_vode><prvi_vv_pretok>62</prvi_vv_pretok><drugi_vv_pretok>95</drugi_vv_pretok><tretji_vv_pretok>130</tretji_vv_pretok></postaja>
<postaja sifra="9030" ge_dolzina="14.231142" ge_sirina="45.573417" kota_0="393.03"><reka>Reka</reka><merilno_mesto>Trnovo</merilno_mesto><ime_kratko>Reka - Trnovo</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>123</vodostaj><pretok>0.84</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>12.6</temp_vode><prvi_vv_pretok>92</prvi_vv_pretok><drugi_vv_pretok>140</drugi_vv_pretok><tretji_vv_pretok>200</tretji_vv_pretok></postaja>
<postaja sifra="9050" ge_dolzina="14.065534" ge_sirina="45.655165" kota_0="342.7"><reka>Reka</reka><merilno_mesto>Cerkvenikov mlin</merilno_mesto><ime_kratko>Reka - Cerkvenikov mlin</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>149</vodostaj><pretok>1.83</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>16.3</temp_vode><prvi_vv_pretok>130</prvi_vv_pretok><drugi_vv_pretok>180</drugi_vv_pretok><tretji_vv_pretok>260</tretji_vv_pretok></postaja>
<postaja sifra="9100" ge_dolzina="14.246457" ge_sirina="45.564655" kota_0="396.7"><reka>Bistrica</reka><merilno_mesto>Ilirska Bistrica</merilno_mesto><ime_kratko>Bistrica - Il. Bistrica</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>100</vodostaj><pretok>0.64</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>9.7</temp_vode><prvi_vv_pretok>19</prvi_vv_pretok><drugi_vv_pretok>26</drugi_vv_pretok><tretji_vv_pretok>33</tretji_vv_pretok></postaja>
<postaja sifra="9108" ge_dolzina="14.233849" ge_sirina="45.551522" kota_0="401"><reka>Molja</reka><merilno_mesto>Zarečica</merilno_mesto><ime_kratko>Molja - Zarečica</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>79</vodostaj><pretok>0.26</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>17.0</temp_vode><prvi_vv_pretok>40</prvi_vv_pretok><drugi_vv_pretok>55</drugi_vv_pretok><tretji_vv_pretok>85</tretji_vv_pretok></postaja>
<postaja sifra="9210" ge_dolzina="13.880788" ge_sirina="45.532914" kota_0="61.13"><reka>Rižana</reka><merilno_mesto>Kubed</merilno_mesto><ime_kratko>Rižana - Kubed</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>76</vodostaj><pretok>0.41</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>11.6</temp_vode><prvi_vv_pretok>40</prvi_vv_pretok><drugi_vv_pretok>70</drugi_vv_pretok><tretji_vv_pretok>100</tretji_vv_pretok></postaja>
<postaja sifra="9240" ge_dolzina="13.817906" ge_sirina="45.543187" kota_0="18.71"><reka>Rižana</reka><merilno_mesto>Dekani</merilno_mesto><ime_kratko>Rižana - Dekani</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>130</vodostaj><temp_vode>14.3</temp_vode><prvi_vv_pretok>50</prvi_vv_pretok><drugi_vv_pretok>90</drugi_vv_pretok><tretji_vv_pretok>130</tretji_vv_pretok></postaja>
<postaja sifra="9280" ge_dolzina="13.643463" ge_sirina="45.466247" kota_0="3.43"><reka>Drnica</reka><merilno_mesto>Pišine</merilno_mesto><ime_kratko>Drnica - Pišine</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>117</vodostaj><pretok>0.09</pretok><pretok_znacilni>srednji pretok</pretok_znacilni><temp_vode>18.0</temp_vode><prvi_vv_pretok>13</prvi_vv_pretok><drugi_vv_pretok>20</drugi_vv_pretok><tretji_vv_pretok>45</tretji_vv_pretok></postaja>
<postaja sifra="9300" ge_dolzina="13.65905" ge_sirina="45.452932" kota_0="9.78"><reka>Dragonja</reka><merilno_mesto>Podkaštel</merilno_mesto><ime_kratko>Dragonja - Podkaštel</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>68</vodostaj><pretok>0.07</pretok><pretok_znacilni>mali pretok</pretok_znacilni><temp_vode>20.1</temp_vode><prvi_vv_pretok>35</prvi_vv_pretok><drugi_vv_pretok>75</drugi_vv_pretok><tretji_vv_pretok>120</tretji_vv_pretok></postaja>
<postaja sifra="9350" ge_dolzina="13.72915" ge_sirina="45.54839" kota_0="-2.09"><reka>Jadransko morje</reka><merilno_mesto>Koper - kapitanija</merilno_mesto><ime_kratko>Jadransko morje - Koper</ime_kratko><datum>2020-06-25 09:00</datum><vodostaj>189</vodostaj><vodostaj_znacilni>srednji vodostaj</vodostaj_znacilni><temp_vode>23.6</temp_vode><prvi_vv_vodostaj>300</prvi_vv_vodostaj><drugi_vv_vodostaj>330</drugi_vv_vodostaj><tretji_vv_vodostaj>350</tretji_vv_vodostaj></postaja>
<postaja sifra="9400" ge_dolzina="13.550017" ge_sirina="45.551116"><reka>Jadransko morje</reka><merilno_mesto>OB Piran (NIB)</merilno_mesto><ime_kratko>Jadransko morje - boja Piran (NIB)</ime_kratko><datum>2020-06-25 09:00</datum><temp_vode>24.4</temp_vode><znacilna_visina_valov></znacilna_visina_valov><smer_valovanja></smer_valovanja></postaja>
<postaja sifra="9420" ge_dolzina="13.535359" ge_sirina="45.601581"><reka>Jadransko morje</reka><merilno_mesto>Tržaški zaliv (Zarja)</merilno_mesto><ime_kratko>Jadransko morje - boja Tržaski zaliv</ime_kratko><datum>2020-06-25 09:00</datum><temp_vode></temp_vode><znacilna_visina_valov></znacilna_visina_valov><smer_valovanja></smer_valovanja></postaja>
</arsopodatki>
XML;
	}


}
