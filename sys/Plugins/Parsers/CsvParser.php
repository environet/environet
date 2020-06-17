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
	 * @var int Number of lines of header to skip
	 */
	private $nHeaderSkip;

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
		$this->nHeaderSkip = $config['nHeaderSkip'];
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
				if(empty($resultLine[$property['symbol']]))
					continue;
				
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
		$console->writeLine('Configuring csv parser', Console::COLOR_YELLOW);

		$console->writeLine('Enter the csv delimiter character. E.g.: a comma in case of comma separated csv files', Console::COLOR_YELLOW);
		$csvDelimiter = $console->ask('Csv delimiter:');

		$console->writeLine('How many lines have to be skipped before data begins', Console::COLOR_YELLOW);
		$nHeaderSkip = $console->ask('Number of Lines:');

		$console->writeLine('In what number column (from left) of the csv file is the identifier of the monitoring point?', Console::COLOR_YELLOW);
		$mPointIdCol = $console->ask('Column number:') - 1;

		$console->writeLine('In what number column (from left) of the csv file is the time of measurement?', Console::COLOR_YELLOW);
		$timeCol = $console->ask('Column number:') - 1;

		$console->writeLine('In what format is the time represented in the file?', Console::COLOR_YELLOW);
		$timeFormat = $console->ask('Time format (for example, the format \'Y-m-d H:i:s\' corresponds to dates such as: 2020-03-15 10:15:00, while \'Y.m.d. H:i\' would match 2020.03.15. 10:15):');

		$console->writeLine('Configuring observed properties', Console::COLOR_YELLOW);
		$console->writeLine('For each observed property that can be found in the csv file, enter the symbol (that it used on the distribution node) first, and then the column number where the values are located.', Console::COLOR_YELLOW);

		$properties = [];
		do {
			$properties[] = self::serializePropertyConfiguration([
				'symbol' => $console->ask("Property symbol:"),
				'column' => $console->ask("Column number:") - 1
			]);
		} while ($console->askYesNo('Do you want to add more properties?'));


		$config = [
			'csvDelimiter' => $csvDelimiter,
			'nHeaderSkip'  => $nHeaderSkip,
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
		$config .= 'nHeaderSkip = ' . $this->nHeaderSkip . "\n";
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


	/**
	 * @inheritDoc
	 */
	public static function getHelp(): string {
		return 'For parsing data in CSV format.';
	}


}
