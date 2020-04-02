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
 * Class CsvParser
 *
 * Parser layer for CSV files
 *
 * @package Environet\Sys\Plugins\Parsers
 * @author  SRG Group <dev@srg.hu>
 */
class CsvParser implements ParserInterface, BuilderLayerInterface {

	const API_TIME_FORMAT_STRING = 'Y-m-d\TH:i:sP';

	/**
	 * @var mixed Delimiter character
	 */
	private $csvDelimiter;

	/**
	 * @var mixed The monitoring point ID's column number
	 */
	private $mPointIdCol;

	/**
	 * @var mixed Time column number
	 */
	private $timeCol;

	/**
	 * @var mixed Time format string
	 */
	private $timeFormat;

	/**
	 * @var array A symbol => column number representation of observable properties
	 */
	private $properties;


	/**
	 * CsvParser constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {

		$this->csvDelimiter = $config['csvDelimiter'];
		$this->mPointIdCol = $config['mPointIdCol'];
		$this->timeCol = $config['timeCol'];
		$this->timeFormat = $config['timeFormat'];
		$this->properties = array_map(function ($propertyString) {
			return [
				'symbol' => explode(';', $propertyString)[0],
				'column' => explode(';', $propertyString)[1]
			];
		}, $config['properties']);
	}


	/**
	 * @inheritDoc
	 * @throws CreateInputXmlException
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::mPointDataArrayFromCSV()
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::meteringPointInputXmlsFromArray()
	 */
	public function parse(string $data): array {
		$dataArray = $this->mPointDataArrayFromCSV($data);

		return $this->meteringPointInputXmlsFromArray($dataArray);
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

		foreach ($lines as $line) {
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
		$values = explode($this->csvDelimiter, $line);
		if (!$values[$this->timeCol]) {
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
		$console->writeLine('Configuring csv parser');
		$csvDelimiter = $console->ask('Csv delimiter:', 1);
		$mPointIdCol = $console->ask('Monitoring point id column number:', 3) - 1;
		$timeCol = $console->ask('Time column number:', 3) - 1;
		$timeFormat = $console->ask('Time format (e.g. Y-m-d H:i:s):', 20);

		$console->writeLine('Configuring properties', Console::COLOR_YELLOW);

		$properties = [];
		do {
			$properties[] = self::serializePropertyConfiguration([
				'symbol' => $console->ask("Property symbol:", 30),
				'column' => $console->ask("Value column number:", 3)
			]);
		} while ($console->askYesNo('Do you want to add more properties?'));


		$config = [
			'csvDelimiter' => $csvDelimiter,
			'mPointIdCol'  => $mPointIdCol,
			'timeCol'      => $timeCol,
			'timeFormat'   => $timeFormat,
			'properties'   => $properties
		];

		return new self($config);
	}


	/**
	 * Create a semicolon delimited string of a property.
	 *
	 * Format: symbol;column
	 *
	 * @param $property
	 *
	 * @return string
	 */
	public static function serializePropertyConfiguration($property): string {
		return $property['symbol'] . ';' . $property['column'];
	}


	/**
	 * @inheritDoc
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::serializePropertyConfiguration()
	 */
	public function serializeConfiguration(): string {
		$config = '';
		$config .= 'csvDelimiter = "' . $this->csvDelimiter . "\"\n";
		$config .= 'mPointIdCol = ' . $this->mPointIdCol . "\n";
		$config .= 'timeCol = ' . $this->timeCol . "\n";
		$config .= 'timeFormat = ' . $this->timeFormat . "\n";

		foreach ($this->properties as $property) {
			$config .= 'properties[] = "' . self::serializePropertyConfiguration($property) . "\"\n";
		}

		return $config;
	}


	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return 'csv parser';
	}


}
