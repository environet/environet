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
	 * @var array General format specifications
	 */
	private $formats;

	/**
	 * @var array Format specifications per observable property
	 */
	private $observedPropertyFormats;

	/**
	 * @var array Tag hierarchy that forms a group of data belonging together (Monitoring point + Measurements)
	 */
	private $commonHierarchy;

	/**
	 * @var array Tag hierarchy that forms a sub-group of data belonging together (Date + Measured Value), relative to $commonHierarchy
	 */
	private $commonMeasurementHierarchy;

	/**
	 * XMLParser constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {

		$this->mode = $config['mode'];

		$commonHierarchyLfU = [
			"hnd-daten",
			"messstelle",
		];

		$commonMeasurementHierarchyLfU = [
					"messwert",
		];

		$formatsLfU = [
			[ 
				"Parameter" => "Monitoring Point",
				"Value" => "MPID",
				"Tag Hierarchy" => [
					"hnd-daten",
					"messstelle",
					"nummer",
				],
			],
			[ 
				"Parameter" => "Year",
				"Value" => "Y",
				"Tag Hierarchy" => [
					"hnd-daten",
					"messstelle",
					"messwert",
					"datum",
					"jahr",
				],
			],
			[ 
				"Parameter" => "Month",
				"Value" => "m",
				"Tag Hierarchy" => [
					"hnd-daten",
					"messstelle",
					"messwert",
					"datum",
					"monat",
				],
			],
			[ 
				"Parameter" => "Day",
				"Value" => "d",
				"Tag Hierarchy" => [
					"hnd-daten",
					"messstelle",
					"messwert",
					"datum",
					"tag",
				],
			],
			[ 
				"Parameter" => "Hour",
				"Value" => "H",
				"Tag Hierarchy" => [
					"hnd-daten",
					"messstelle",
					"messwert",
					"datum",
					"stunde",
				],
			],
			[ 
				"Parameter" => "Minute",
				"Value" => "i",
				"Tag Hierarchy" => [
					"hnd-daten",
					"messstelle",
					"messwert",
					"datum",
					"minute",
				],
			],
			[
				"Parameter" => "ObservableProperyValue",
				"Value" => "h",   // Symbol
				"Unit" => "cm",
				"Tag Hierarchy" => [
					"hnd-daten",
					"messstelle",
					"messwert",
					"wert",
				],
			],
			[
				"Parameter" => "ObservableProperyValue",
				"Value" => "Q",   // Symbol
				"Unit" => "m3/s",
				"Tag Hierarchy" => [
					"hnd-daten",
					"messstelle",
					"messwert",
					"wert",
				],
			],
			// Parameter => ObservablePropertySymbol if symbol is coded in XML
			// Parameter => ObservablePropertyUnit if unit is coded in XML
		];

		$observedPropertyFormatsLfU = [
			[ 
				"Symbol" => "h",
				"Unit" => "cm",
				"Tag Hierarchy Value" => [
					"hnd-daten",
					"messstelle",
					"messwert",
					"wert",
				],
				"Tag Hierarchy Unit" => [],
			],
			[ 
				"Symbol" => "Q",
				"Unit" => "m3/s",
				"Tag Hierarchy Value" => [
					"hnd-daten",
					"messstelle",
					"messwert",
					"wert",
				],
				"Tag Hierarchy Unit" => [],
			],

		];

		if (true) {
			$this->commonHierarchy = $commonHierarchyLfU;
			$this->commonMeasurementHierarchy = $commonMeasurementHierarchyLfU;
			$this->formats = $formatsLfU;
			$this->observedPropertyFormats = $observedPropertyFormatsLfU;
		}

	}

	private function getAndStripOneCommonElement(array &$formats) : string {
		if (sizeof($formats) == 0) return "";
		if (sizeof($formats) == 1 && sizeof($formats[0]["Tag Hierarchy"] == 0)) return "";
		if (sizeof($formats) == 1 && sizeof($formats[0]["Tag Hierarchy"] > 0))	return array_shift($formats[0]["Tag Hierarchy"]);
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

//$tmp2 = $xml->xpath("messstelle");
//$tmp = $tmp2[0]->xpath("nummer");
//ob_start();
//var_dump($tmp);
//echo "XXX: " . ob_get_clean(). "\r\n";

		if ($hierarchyCounter>10) {
			throw new Exception("XML hierarchy deeper than 10");
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
		if ($xpathCommonElements == "/") {
			echo "Error condition 1: Missing information";
			//throw new Exception("Unresolved information: " . $out);
			return [];
		}

		// get groups
		$flatList = [];
		$groups = $xml->xpath($xpathCommonElements);
		echo "xpathCommonElements: " . $xpathCommonElements . "\r\n";
		echo "Number of groups: " . sizeof($groups) . "\r\n";

		if ($groups == null) {
			throw new Exception("Given elements do not exist in file: " . $xpathCommonElements);
		}

		foreach ($groups as $group) {
			ob_start();
			var_dump($group);
			echo "group: " . ob_get_clean() . "\r\n";
			// count elements and resolve those which are unique
			$nResolved = 0;
			$groupResolved = $resolved;
			$formatsNew = [];
			foreach ($formats as $format) {
				echo "format.Parameter: " . $format["Parameter"] . "\r\n";
				$xpath = implode('/', $format["Tag Hierarchy"]);
				echo "xpath: " . $xpath . "\r\n";
				$subXml = $group->xpath($xpath);
				ob_start();
				var_dump($subXml);
				echo "subXml: " . ob_get_clean() . "\r\n";
				if ($subXml == null) {
					throw new Exception("Given elements do not exist in file: " . $xpath);
				}
				if (sizeof($subXml) == 0) {
					// do nothing
					echo "do nothing.\r\n";
				}
				else if (sizeof($subXml) == 1) {
					$item = [];
					$item["Type"] = $format["Parameter"];
					$item["Value"] = $subXml[0]->__toString();
					$item["Format"] = $format["Value"];
					$item["Unit"] = $format["Unit"];
					array_push($groupResolved, $item);
					++$nResolved;
					echo "resolved ".$item["Type"].", value=".$item["Value"]."\r\n";
				} else {
					array_push($formatsNew, $format);
					echo "not yet resolved.\r\n";
				}
			}

			if (sizeof($formatsNew) > 1) {
				// do recursion 
				echo "do recursion\r\n";
				$flatList = array_merge($flatList, $this->diveIntoHierarchy($group, $formatsNew, $groupResolved, $hierarchyCounter+1));
			} else {
				// Finish condition 3: Success
				// all information available. Return flat list entry from resolved
				echo "all resolved.\r\n";
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

	/**
	 * @inheritDoc
	 * @throws CreateInputXmlException
	 */
	public function parse(string $data): array {
		
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

		// strip top-level element from formats
		$topLevel = $this->getAndStripOneCommonElement($this->formats);
		if ($topLevel == "") {
			throw new Exception("XML definition does not have a top-level element");
		}

		$flatList = $this->diveIntoHierarchy($xml, $this->formats, [], 0);

		ob_start();
		var_dump($flatList);
		echo "flatList: " . ob_get_clean() . "\r\n";
		//$dataArray = $this->mPointDataArrayFromCSV($data);
		//return $this->meteringPointInputXmlsFromArray($dataArray);
		return [];
	}


	/**
	 * Create an associative array from the input CSV string
	 * Format: [mpointId => [propertySymbol => results]]
	 *
	 * @param string $csv
	 *
	 * @return array
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::parseResultLine()
	 */
	private function mPointDataArrayFromCSV(string $csv): array {
		$resultArray = [];

		$lines = explode("\n", $csv);

		$lineCount = 0;
		foreach ($lines as $line) {
			++$lineCount;
			if ($lineCount <= $this->nHeaderSkip) {
				continue;
			}
			$resultLine = $this->parseResultLine($line);
			if (empty($resultLine)) {
				continue;
			}

			if (!array_key_exists($resultLine['mPointId'], $resultArray)) {
				$resultArray[$resultLine['mPointId']] = [];
			}

			// Initialize time series for properties with an empty array
			foreach ($this->properties as $property) {
				if (!array_key_exists($property['symbol'], $resultArray[$resultLine['mPointId']])) {
					$resultArray[$resultLine['mPointId']][$property['symbol']] = [];
				}
			}

			foreach ($this->properties as $property) {
				$resultArray[$resultLine['mPointId']][$property['symbol']] = array_merge(
					$resultArray[$resultLine['mPointId']][$property['symbol']],
					[
						[
							'time'  => $resultLine['time'],
							'value' => $resultLine[$property['symbol']]
						]
					]
				);
			}
		}

		return $resultArray;
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
	 * Parse one line of the CSV input string.
	 *
	 * @param $line
	 *
	 * @return array
	 */
	private function parseResultLine($line): array {
		$values = array_map('trim', explode($this->csvDelimiter, $line));
		if (!array_key_exists($this->timeCol, $values)) {
			return [];
		}

		$data = [
			'mPointId' => $values[$this->mPointIdCol],
			'time'     => DateTime::createFromFormat($this->timeFormat, $values[$this->timeCol])->format(self::API_TIME_FORMAT_STRING),
		];

		foreach ($this->properties as $property) {
			$data[$property['symbol']] = $values[$property['column']];
		}


		return $data;
	}


	/**
	 * @inheritDoc
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::serializePropertyConfiguration()
	 */
	public static function create(Console $console): ParserInterface {
		$console->writeLine('');
		$console->writeLine('Configuring XML parser', Console::COLOR_YELLOW);

		$mode = $console->ask("Enter mode of operation [preliminary] (LfU)", 10);

		$config = [
			'mode' => $mode,
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
