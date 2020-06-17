<?php

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
	 * @var array Tag hierarchy that forms a group of data belonging together
	 */
	private $commonHierarchy;


	/**
	 * XMLParser constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {

		$this->mode = $config['mode'];

		$commonHierarchyLfU = [
			"<hnd-daten>",
			"<messtelle>",
		];

		$formatsLfU = [
			[ 
				"Parameter" => "Monitoring Point",
				"Value" => "MPID",
				"Tag Hierarchy" => [
					"<nummer>",
				],
			],
			[ 
				"Parameter" => "Year",
				"Value" => "Y",
				"Tag Hierarchy" => [
					"<messwert>",
					"<datum>",
					"<jahr>",
				],
			],
			[ 
				"Parameter" => "Month",
				"Value" => "m",
				"Tag Hierarchy" => [
					"<messwert>",
					"<datum>",
					"<monat>",
				],
			],
			[ 
				"Parameter" => "Day",
				"Value" => "d",
				"Tag Hierarchy" => [
					"<messwert>",
					"<datum>",
					"<tag>",
				],
			],
			[ 
				"Parameter" => "Hour",
				"Value" => "H",
				"Tag Hierarchy" => [
					"<messwert>",
					"<datum>",
					"<stunde>",
				],
			],
			[ 
				"Parameter" => "Minute",
				"Value" => "i",
				"Tag Hierarchy" => [
					"<messwert>",
					"<datum>",
					"<minute>",
				],
			],
		];

		$observedPropertyFormatsLfU = [
			[ 
				"Symbol" => "h",
				"Unit" => "cm",
				"Tag Hierarchy Value" => [
					"<messwert>",
					"<wert>",
				],
				"Tag Hierarchy Unit" => [],
			],
			[ 
				"Symbol" => "Q",
				"Unit" => "m3/s",
				"Tag Hierarchy Value" => [
					"<messwert>",
					"<wert>",
				],
				"Tag Hierarchy Unit" => [],
			],

		];

		if (true) {
			$this->commonHierarchy = $commonHierarchyLfU;
			$this->formats = $formatsLfU;
			$this->observedPropertyFormats = $observedPropertyFormatsLfU;
		}

	}


	/**
	 * @inheritDoc
	 * @throws CreateInputXmlException
	 */
	public function parse(string $xmldata): array {
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
