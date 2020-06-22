<?php

// TODOS:
// - Get units from XML if available
// - Get observable property symbold from XML if available

namespace Environet\Sys\Plugins\Parsers;

use DateTime;
use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\ParserInterface;
use Environet\Sys\Xml\CreateInputXml;
use Environet\Sys\Xml\Exceptions\CreateInputXmlException;
use Environet\Sys\Xml\Model\InputXmlData;
use Environet\Sys\Xml\Model\InputXmlPropertyData;
use SimpleXMLElement;

/**
 * Class XMLParser
 *
 * Parser layer for XML files
 *
 * @package Environet\Sys\Plugins\Parsers
 * @author  SRG Group <dev@srg.hu>, STASA <info@stasa.de>
 */
class XmlParser implements ParserInterface, BuilderLayerInterface {

	const API_TIME_FORMAT_STRING = 'Y-m-d\TH:i:sP';

	/**
	 * @var string Mode of operation as workaround while initialization file is not implemented: "LfU"
	 */
	private $mode;

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
	private $formatsFile;

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

		$this->mode = $config['mode'];
		$this->separatorThousands = $config['separatorThousands'];
		$this->separatorDecimals = $config['separatorDecimals'];
		$this->formatsFilename = $config['formatsFilename'];
		

		$configurationsPath = SRC_PATH . '/conf/plugins/configurations/';
		$formats = file_get_contents($configurationsPath . $this->formatsFilename);
		$this->formats = JSON_decode($formats, true);

		// Parameter => ObservablePropertySymbol if symbol is coded in XML
		// Parameter => ObservablePropertyUnit if unit is coded in XML
	}

	private function getAndStripOneCommonElement(array &$formats) : string {
		if (sizeof($formats) == 0) return "";
		if (sizeof($formats) == 1 && sizeof($formats[0]["Tag Hierarchy"]) == 0) return "";
		if (sizeof($formats) == 1 && sizeof($formats[0]["Tag Hierarchy"]) > 0)	return array_shift($formats[0]["Tag Hierarchy"]);
		$difference = false;
		for ($i = 1; $i < sizeof($formats); ++$i) {
			if (sizeof($formats[$i]["Tag Hierarchy"]) == 0 || sizeof($formats[$i-1]["Tag Hierarchy"]) == 0 || 
				$formats[$i]["Tag Hierarchy"][0] != $formats[$i-1]["Tag Hierarchy"][0]) {
				$difference = true;
			}
		}
		if ($difference) return "";
		$result = $formats[0]["Tag Hierarchy"][0];
		for ($i = 0; $i < sizeof($formats); ++$i) {
			array_shift($formats[$i]["Tag Hierarchy"]);
		}
		return $result;
	}

	private function diveIntoHierarchy(SimpleXMLElement $xml, array $formats, array $resolved, int $hierarchyCounter) : array {
		/*
		echo "-----------------------------------------\r\n";
		echo "diveIntoHierary called.\r\n";
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

		if ($hierarchyCounter>10) {
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
			if ($common != "") array_push($commonElements, $common);
		} while ($common != "");
		$xpathCommonElements = implode('/', $commonElements);

		// Finish condition 1: No common elements, but unresolved information
		if ($xpathCommonElements == "") {
			echo "Error condition 1: Missing information";
			//throw new Exception("Unresolved information: " . $out);
			return [];
		}

		// get groups
		$flatList = [];
		$groups = $xml->xpath($xpathCommonElements);
		//echo "xpathCommonElements: " . $xpathCommonElements . "\r\n";
		//echo "Number of groups: " . sizeof($groups) . "\r\n";

		if ($groups == null) {
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
				//echo "xpath: " . $xpath . "\r\n";
				if ($xpath != "") {
					// desired information is sub-item of group
					$subXml = $group->xpath($xpath);
				} else {
					// desired information is group itself
					$subXml = $group;
				}
				//ob_start();
				//var_dump($subXml);
				//echo "subXml: " . ob_get_clean() . "\r\n";
				if ($subXml == null) {
					throw new \Exception("Given elements do not exist in file: " . $xpath);
				}
				if (sizeof($subXml) == 0) {
					// do nothing
					//echo "do nothing.\r\n";
				}
				else if (sizeof($subXml) == 1) {
					$item = [];
					$item["Type"] = $format["Parameter"];
					$item["Value"] = $subXml[0]->__toString();
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
				$flatList = array_merge($flatList, $this->diveIntoHierarchy($group, $formatsNew, $groupResolved, $hierarchyCounter+1));
			} else {
				// Finish condition 3: Success
				// all information available. Return flat list entry from resolved
				//echo "all resolved.\r\n";
				array_push($flatList, $groupResolved);
			}
		}	// group

		return $flatList;
	}

	private function getParameter(array $list, string $parameterName, string $parameterValue) : array {
		$result = [];
		foreach($list as $elem) {
			if (array_key_exists($parameterName, $elem) && $elem[$parameterName] == $parameterValue) {
				$result = $elem;
				break;
			}
		}
		return $result;
	}

	private function delete(array &$list, string $parameterName, string $parameterValue)
	{
		$toDelete = [];
		foreach($list as $key => $elem) {
			if (array_key_exists($parameterName, $elem) && $elem[$parameterName] == $parameterValue) {
				array_push($toDelete, $key);
			}
		}
		foreach($toDelete as $key) {
			\array_splice($list, $key, 1);
		}
	}

	private function exists(array $list, string $parameter, string $value) : bool {
		$exists = false;
		foreach($list as $element) {
			if ($element[$parameter] == $value) {
				$exists = true;
				break;
			}
		}
		return $exists;
	}

	private function assembleDate(array &$entry) {
		$DateTime = $this->getParameter($entry, "Type", "DateTime");
		$Date = $this->getParameter($entry, "Type", "Date");
		$Time = $this->getParameter($entry, "Type", "Time");
		$Year = $this->getParameter($entry, "Type", "Year");
		$Month = $this->getParameter($entry, "Type", "Month");
		$Day = $this->getParameter($entry, "Type", "Day");
		$Hour = $this->getParameter($entry, "Type", "Hour");
		$Minute = $this->getParameter($entry, "Type", "Minute");

		/*
		$DateTime = [
			"Type" => "DateTime",
			"Value" => "19.6.2020 12:34",
			"Format" => "d.m.Y H:i",
			"Unit" => null
		];
		
		$Date = [
			"Type" => "Date",
			"Value" => "19.6.2020",
			"Format" => "d.m.Y",
			"Unit" => null
		];

		$Time = [
			"Type" => "Time",
			"Value" => "15:39",
			"Format" => "H:i",
			"Unit" => null
		];
		*/

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
			$result["Value"] = date(self::API_TIME_FORMAT_STRING, $t);
			$this->delete($entry, "Type", "Year");
			$this->delete($entry, "Type", "Month");
			$this->delete($entry, "Type", "Day");
			$this->delete($entry, "Type", "Hour");
			$this->delete($entry, "Type", "Minute");
		} 
		else if ($Date && $Time) 
		{
			$date = DateTime::createFromFormat($Date["Format"].' '.$Time["Format"], $Date["Value"].' '.$Time["Value"]);
			if (!$date) {
				throw new \Exception("Invalid date or time format");
			}
			$result["Value"] = $date->format(self::API_TIME_FORMAT_STRING);
			$this->delete($entry, "Type", "Date");
			$this->delete($entry, "Type", "Time");
		} 
		else if ($DateTime) 
		{
			$date = DateTime::createFromFormat($DateTime["Format"], $DateTime["Value"]);
			if (!$date) {
				throw new \Exception("Invalid datetime format");
			}
			$result["Value"] = $date->format(self::API_TIME_FORMAT_STRING);
			$this->delete($entry, "Type", "DateTime");
		} else {
			throw new \Exception("Incomplete date");
		}
		array_push($entry, $result);
	}

	private function assembleDates(array &$flatList) {
		foreach($flatList as &$entry) {
			$this->assembleDate($entry);
		}
	}

	// remove thousands separator, change decimal separator, add entry for unit if not available
	private function convertValue(array &$entry) {
		$unit = $this->getParameter($entry, "Type", "ObservablePropertyUnit");
		foreach($entry as &$v) {
			if ($v["Type"] == "ObservablePropertyValue") {
				if ($this->separatorThousands != "") $v["Value"] = str_replace($this->separatorThousands, "", $v["Value"]);
				if ($this->separatorDecimals != "." && $this->separatorDecimals != "") $v["Value"] = str_replace($this->separatorDecimals, ".", $v["Value"]);
				if (!$unit) {
					$elem = [
						"Type" => "ObservablePropertyUnit",
						"Value" => $v["Unit"],
						"Format" => null,
						"Unit" => null,
					];
					array_push($entry, $elem);
				}
			}
		}
	}

	private function convertValues(array &$flatList) {
		foreach($flatList as &$entry) {
			$this->convertValue($entry);
		}
	}

	/**
	 * @inheritDoc
	 * @throws CreateInputXmlException
	 */
	public function parse(string $data, array $meta = null): array {
		
		//echo $data;

		$data = <<<XML
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
<nummer>10032010</nummer>
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
		$xml = new SimpleXMLElement($data);

		$formats = $this->formats;

		// strip top-level element from formats
		$topLevel = $this->getAndStripOneCommonElement($formats);
		if ($topLevel == "") {
			throw new \Exception("XML definition does not have a top-level element");
		}

		$flatList = $this->diveIntoHierarchy($xml, $formats, [], 0);

		$this->assembleDates($flatList);

		// Add missing information in file from API-Call (Monitoring Point or Observable Property symbol)
		if (sizeof($flatList) > 0 && $meta) {
			$mp = $this->getParameter($flatList[0], "Type", "Monitoring Point");
			if (!$mp) {
				$elem = [
					"Type" => "Monitoring Point",
					"Value" => $meta["Monitoring Point"],
					"Format" => null,
					"Unit" => null,
				];
				$this->addToEach($flatList, $elem);
			}
			$obs = $this->getParameter($flatList[0], "Type", "ObservablePropertySymbol");
			if (!$obs) {
				$elem = [
					"Type" => "ObservablePropertySymbol",
					"Value" => $meta["ObservablePropertySymbol"],
					"Format" => null,
					"Unit" => null,
				];
				foreach($flatList as &$entry) {
					// First delete all occurrences of ObservablePropertyValue with wrong symbol
					$toDelete = [];
					foreach($entry as $key => $e) {
						if ($e["Type"] == "ObservablePropertyValue" &&
						    $e["Format"] != $elem["Value"]) {
							array_push($toDelete, $key);
						}
					}
					foreach($toDelete as $key) {
						\array_splice($entry, $key, 1);
					}
					//$this->delete($entry, "Format", $elem["Format"]);
					// then add ObservablePropertySymbol
					array_push($entry, $elem);
				}
			}
		}

		$this->convertValues($flatList);

		//ob_start();
		//var_dump($flatList);
		//echo "flatList: " . ob_get_clean() . "\r\n";

		$resultArray = [];
		$properties = [];
		foreach($flatList as $line) {
			$mp = $this->getParameter($line, "Type", "Monitoring Point")["Value"];

			if (!array_key_exists($mp, $resultArray)) {
				$resultArray[$mp] = [];
			}

			$obs = $this->getParameter($line, "Type", "ObservablePropertySymbol")["Value"];

			if (!array_key_exists($obs, $resultArray[$mp])) {
				$resultArray[$mp][$obs] = [];
			}

			$time = $this->getParameter($line, "Type", "DateTime")["Value"];
			$value = strval($this->getParameter($line, "Type", "ObservablePropertyValue")["Value"]);
			$unit = $this->getParameter($line, "Type", "ObservablePropertyUnit")["Value"];

			$resultArray[$mp][$obs] = array_merge($resultArray[$mp][$obs],
				[
					[
						'time' => $time,
						'value' => $value,
						//'unit' => $unit,
					]
				]
			);

		}	

//ini_set('xdebug.var_display_max_depth', '10');
//ini_set('xdebug.var_display_max_children', '256');
//ini_set('xdebug.var_display_max_data', '1024');

		//ob_start();
		//var_dump($resultArray);
		//echo "resultArray: " . ob_get_clean() . "\r\n";

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

		$mode = $console->ask("Enter mode of operation [preliminary] (LfU)", 10);
		$separatorThousands = $console->ask('Separator for groups of thousands in values. May be empty. Example: , for 12,040.01 cm');
		$separatorDecimals = $console->ask('Separator for decimals. Example: . for 142.3 cm');
		$formatsFilename = $console->ask('Filename for xml format definitions');

		$config = [
			'mode' => $mode,
			'separatorThousands' => $separatorThousands,
			'separatorDecimals' => $separatorDecimals,
			'formatsFilename' => $formatsFilename,
		];

		return new self($config);
	}


	/**
	 * @inheritDoc
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::serializePropertyConfiguration()
	 */
	public function serializeConfiguration(): string {
		$config = '';
		$config .= 'mode = "' . $this->mode . "\"\n";
		$config .= 'separatorThousands = "' . $this->separatorThousands . "\"\n";
		$config .= 'separatorDecimals = "' . $this->separatorDecimals . "\"\n";
		$config .= 'formatsFilename = "' . $this->formatsFilename . "\"\n";

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
