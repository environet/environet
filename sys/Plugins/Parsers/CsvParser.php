<?php

namespace Environet\Sys\Plugins\Parsers;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\ParserInterface;
use Environet\Sys\Plugins\PluginBuilder;
use Environet\Sys\Plugins\Resource;
use Environet\Sys\Plugins\WithConversionsConfigTrait;
use Environet\Sys\Xml\CreateInputXml;
use Environet\Sys\Xml\Exceptions\CreateInputXmlException;
use Environet\Sys\Xml\Model\InputXmlData;
use Environet\Sys\Xml\Model\InputXmlPropertyData;
use Exception;
use SimpleXMLElement;

/**
 * Class CsvParser
 *
 * Parser layer for CSV files
 *
 * @package Environet\Sys\Plugins\Parsers
 * @author  SRG Group <dev@srg.hu>
 */
class CsvParser extends AbstractParser implements BuilderLayerInterface {

	use WithConversionsConfigTrait;

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
	 * @var mixed Value to ignore
	 */
	private $skipValue;

	/**
	 * @var mixed Time format string
	 */
	private $timeFormat;

	/**
	 * @var mixed The level where the observed property can be found in the source (row / column)
	 */
	private $propertyLevel = 'column';

	/**
	 * @var array A symbol => column number representation of observable properties
	 */
	private $propertySymbolsToColumns;

	/**
	 * @var int The observed property symbols column number
	 */
	private $propertySymbolColumn;

	/**
	 * @var int The observed property values column number
	 */
	private $propertyValueColumn;

	/**
	 * @var string Filename for JSON file with conversions of variables
	 */
	private $conversionsFilename;


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
		$this->skipValue = $config['skipValue'];
		$this->timeFormat = $config['timeFormat'];
		$this->conversionsFilename = $config['conversionsFilename'];

		if ($config['propertyLevel']) {
			$this->propertyLevel = $config['propertyLevel'];
		}

		$this->propertySymbolsToColumns = array_map(function ($propertyString) {
			if ($this->propertyLevel === 'column') {
				return [
					'symbol' => explode(';', $propertyString)[0],
					'column' => explode(';', $propertyString)[1]
				];
			} else {
				return [
					'symbol' => $propertyString
				];
			}
		}, $config['properties']);

		$this->propertySymbolColumn = $config['propertySymbolColumn'];
		$this->propertyValueColumn = $config['propertyValueColumn'];

		parent::__construct($config);
	}


	/**
	 * @inheritDoc
	 * @throws CreateInputXmlException
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::mPointDataArrayFromCSV()
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::meteringPointInputXmlsFromArray()
	 */
	public function parse(Resource $resource): array {
		$propertiesOriginal = $this->propertySymbolsToColumns;
		$properties = $this->propertySymbolsToColumns;
		if ($resource->getPropertySymbols()) {
			// Delete observed properties which are not requested.
			// This is necessary because in some csv files only the requested observed property is contained,
			// but for each observed property in the same column. (e.g. DWD)
			// So without deleting the non-requested observed properties here, in such a case
			// the file would be misinterpreted: Every configured observed property would have the value of
			// the requested observed property.
			foreach ($properties as $key => &$entry) {
				$symbol = $entry["symbol"];
				if (!in_array($symbol, $resource->getPropertySymbols())) {
					unset($properties[$key]);
				}
			}
			$this->propertySymbolsToColumns = array_values($properties);
		}

		$dataArray = $this->mPointDataArrayFromCSV($resource);

		$result = $this->meteringPointInputXmlsFromArray($dataArray);

		$this->propertySymbolsToColumns = $propertiesOriginal;

		return $result;
	}


	/**
	 * Create an associative array from the input CSV string
	 * Format: [mpointId => [propertySymbol => results]]
	 *
	 * @param Resource $resource
	 *
	 * @return array
	 * @throws Exception
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::parseResultLine()
	 */
	private function mPointDataArrayFromCSV(Resource $resource): array {
		$csv = $resource->getContents();

		$globalTime = null;
		if ($this->timeInFilenameFormat) {
			//Should use global time for whole file
			try {
				//Match date based on regex
				$hasMatch = preg_match('/.*(?P<time>' . dateFormatToRegex($this->timeInFilenameFormat) . ')/', $resource->getName(), $match);
			} catch (Exception $exception) {
				$hasMatch = false;
			}
			if (!$hasMatch) {
				return [];
			}
			//Create DateTime
			$globalTime = DateTime::createFromFormat($this->timeInFilenameFormat, $match['time'], $this->getTimeZone());
			$globalTime->setTimezone(new DateTimeZone('UTC'));
		}

		$resultArray = [];

		$lines = explode("\n", $csv);

		$skipValues = [''];
		if ($this->skipValue) {
			$skipValues[] = $this->skipValue;
		}

		$fixedMPoint = null;
		$fixedObservedProperty = null;
		$lineCount = 0;
		$allskip = true;    // signals whether file consists only of skip values
		foreach ($lines as $line) {
			++ $lineCount;

			//parse or skip header
			if ($lineCount <= $this->nHeaderSkip) {
				//Process header
				$values = array_map('trim', explode($this->csvDelimiter, $line));
				if (count($values) < 2) {
					//Not a separated header, skip it
					continue;
				}
				//Find a header keyword in the line
				$prop = $this->getHeaderKeyword($values[0]);
				if ($prop === 'OBS') {
					//Observed property header found, set fixed property
					$fixedObservedProperty = $this->mapToDistributionSymbol($values[1]);
				}
				if ($prop === 'MPID') {
					//Monitoring point header found, set fixed mpoint id
					$fixedMPoint = $values[1];
				}
				continue;
			}

			if (empty($line)) {
				continue;
			}
			$resultLine = $this->parseResultLine($line, $globalTime, $fixedMPoint);
			if (empty($resultLine)) {
				continue;
			}

			if ($this->onlyTimes && !(isset($resultLine['time']) && $this->isAllowedByOnlyTimes($resultLine['time']))) {
				// Skip if time is not in allowed times
				continue;
			}

			foreach ($this->propertySymbolsToColumns as $property) {
				if (!is_null($fixedObservedProperty) && $property['symbol'] !== $fixedObservedProperty) {
					continue;
				}
				if ($this->isSkipValue($resultLine, $property['symbol'], $skipValues)) {
					continue;
				}

				$allskip = false;

				//Init mpoint property in resultArray
				if (!array_key_exists($resultLine['mPointId'], $resultArray)) {
					$resultArray[$resultLine['mPointId']] = [];
				}

				//Init symbol property in resultArray
				if (!array_key_exists($property['symbol'], $resultArray[$resultLine['mPointId']])) {
					$resultArray[$resultLine['mPointId']][$property['symbol']] = [];
				}

				//Add values
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

		if ($allskip) {
			Console::getInstance()->writeLineDp("Resource contains only skip values: {$resource->getName()}", null, null, true);
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

		foreach ($mPointsArray as $mPointId => $properties) {
			array_push($payloads, (new CreateInputXml())->generateXml(new InputXmlData($mPointId, $this->inputXmlDataFromArray($properties))));
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
	 * @param                        $line
	 * @param DateTimeInterface|null $time     A global time for the whole file
	 * @param string|null            $mPointId A pre-defined, fixed mpoint id
	 *
	 * @return array
	 * @throws Exception
	 */
	private function parseResultLine($line, ?DateTimeInterface $time = null, string $mPointId = null): array {
		$values = array_map('trim', explode($this->csvDelimiter, $line));

		if (!$time) {
			if (!array_key_exists($this->timeCol, $values)) {
				// No time set, and no global time set
				return [];
			}

			$time = DateTime::createFromFormat($this->timeFormat, $values[$this->timeCol], $this->getTimeZone());

			if (!$time) {
				// Couldn't parse time
				Console::getInstance()->writeLineDp('Couldn\'t parse time in row: ' . $line, null, null, true);

				return [];
			}
		}

		//Set timezone to UTC
		$time->setTimezone(new DateTimeZone('UTC'));

		if (is_null($mPointId) && !array_key_exists($this->mPointIdCol, $values) || $values[$this->mPointIdCol] === '') {
			return [];
		}

		$data = [
			'mPointId' => $mPointId ?: $values[$this->mPointIdCol],
			'time'     => $time->format(self::API_TIME_FORMAT_STRING)
		];

		switch ($this->propertyLevel) {
			case 'row':
				$symbol = $this->mapToDistributionSymbol($values[$this->propertySymbolColumn]);
				if (!$symbol) {
					Console::getInstance()->writeLineDp('Unknown symbol: ' . $values[$this->propertySymbolColumn], null, null, true);
				}
				$data[$symbol] = $values[$this->propertyValueColumn];

				break;
			default:
				foreach ($this->propertySymbolsToColumns as $property) {
					$data[$property['symbol']] = $values[$property['column']];
				}
		}

		return $data;
	}


	/**
	 * Map symbol name based on conversions config
	 *
	 * @param $symbol
	 *
	 * @return string
	 * @throws Exception
	 */
	private function mapToDistributionSymbol(string $symbol): string {
		if ($this->conversionsFilename) {
			$conversionsConfig = $this->getConversionsConfig();
			foreach ($conversionsConfig['observedPropertyConversions'] as $key => $value) {
				if ($value == $symbol) {
					return $key;
				}
			}
		}

		return $symbol;
	}


	/**
	 * Get information from a CSV header row
	 *
	 * @param string $keyword
	 *
	 * @return string
	 * @throws Exception
	 */
	private function getHeaderKeyword(string $keyword): ?string {
		if ($this->conversionsFilename) {
			$conversionsConfig = $this->getConversionsConfig();
			foreach ($conversionsConfig['header'] ?? [] as $key => $value) {
				if ($value == $keyword) {
					return $key;
				}
			}
		}

		return null;
	}


	/**
	 * @inheritDoc
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::serializePropertyConfiguration()
	 */
	public static function create(Console $console, PluginBuilder $builder): ParserInterface {
		$console->writeLine('');
		$console->writeLine('Configuring csv parser', Console::COLOR_YELLOW);

		$timeZone = self::createTimeZoneConfig($console);

		$console->writeLine('Enter the csv delimiter character. E.g.: a comma in case of comma separated csv files', Console::COLOR_YELLOW);
		$csvDelimiter = $console->ask('Csv delimiter:');

		$console->writeLine('How many lines have to be skipped before data begins', Console::COLOR_YELLOW);
		$nHeaderSkip = $console->ask('Number of Lines:');

		$console->writeLine('In what number column (from left) of the csv file is the identifier of the monitoring point?', Console::COLOR_YELLOW);
		$mPointIdCol = $console->ask('Column number:') - 1;

		$timeCol = $timeFormat = null;
		if (!($timeInFilenameFormat = self::createTimeInFilenameConfig($console))) {
			//Times are in csv, not in filename - default
			$console->writeLine('In what number column (from left) of the csv file is the time of measurement?', Console::COLOR_YELLOW);
			$timeCol = $console->ask('Column number:') - 1;

			$console->writeLine('In what format is the time represented in the file?', Console::COLOR_YELLOW);
			$timeFormat = $console->ask('Time format (for example, the format \'Y-m-d H:i:s\' corresponds to dates such as: 2020-03-15 10:15:00, while \'Y.m.d. H:i\' would match 2020.03.15. 10:15):');
		}

		$console->writeLine('Skip values with exact values: (if the value of a property matches the entered value, the row will be ignored)', Console::COLOR_YELLOW);
		$skipValue = $console->ask('Skip value:');

		$onlyTimes = self::createOnlyTimesConfig($console);

		$console->writeLine('Configuring observed properties', Console::COLOR_YELLOW);

		$console->writeLine('In what way are the values in the files matched to the observed property symbols?');
		$console->writeLine('Choose \'column\' if the values of an observed property have their own column in the files');
		$console->writeLine('Choose \'row\' if the rows have a column containing observed property symbols that specify which symbol the value belongs to');
		$propertyLevel = static::chooseObservedPropertyLevel(Console::getInstance());

		if ($propertyLevel === 'column') {
			$console->writeLine('For each observed property that can be found in the csv file, enter the symbol (that is used on the distribution node) first, and then the column number where the values are located.', Console::COLOR_YELLOW);

			$properties = [];
			do {
				$properties[] = self::serializePropertyConfiguration([
					'symbol' => $console->ask("Property symbol:"),
					'column' => $console->ask("Column number:") - 1
				]);
			} while ($console->askYesNo('Do you want to add more properties?'));
		} else {
			$console->writeLine('Conversions', Console::COLOR_YELLOW);
			$console->writeLine('Observed properties in your files might have a name that differs from the one used by the distribution node for the same property. You can set up a json file to specify the necessary conversions.');
			$conversionsFilename = $console->askWithDefault("Filename of conversion specifications", '');

			$console->writeLine('For each observed property that you want to parse from the csv file, enter its symbol (that is used on the distribution node)', Console::COLOR_YELLOW);
			$properties = [];
			do {
				$properties[] = $console->ask("Property symbol:");
			} while ($console->askYesNo('Do you want to add more properties?'));

			$propertySymbolColumn = $console->ask("Property symbol column number:") - 1;
			$propertyValueColumn = $console->ask("Property value column number:") - 1;
		}

		$config = [
			'csvDelimiter'         => $csvDelimiter,
			'timeZone'             => $timeZone,
			'onlyTimes'            => $onlyTimes,
			'nHeaderSkip'          => $nHeaderSkip,
			'mPointIdCol'          => $mPointIdCol,
			'timeCol'              => $timeCol,
			'skipValue'            => $skipValue,
			'timeFormat'           => $timeFormat,
			'timeInFilenameFormat' => $timeInFilenameFormat,
			'properties'           => $properties,
			'propertyLevel'        => $propertyLevel,
			'conversionsFilename'  => $conversionsFilename,
			'propertySymbolColumn' => $propertySymbolColumn,
			'propertyValueColumn'  => $propertyValueColumn
		];

		return new self($config);
	}


	/**
	 * Ask for alternative if the current layer has any.
	 *
	 * @param Console $console
	 *
	 * @return mixed
	 */
	private static function chooseObservedPropertyLevel(Console $console): string {
		$alternatives = ['column', 'row'];

		foreach (['column', 'row'] as $i => $alternative) {
			$console->writeLine($i + 1 . ": " . $alternative);
		}
		$console->writeLine('');
		$choice = $console->askOption("Enter a number for your choice:");

		return $alternatives[(int) $choice - 1];
	}


	/**
	 * @param array  $resultLine
	 * @param string $symbol
	 *
	 * @param array  $skipValues
	 *
	 * @return bool
	 */
	private function isSkipValue(array $resultLine, string $symbol, array $skipValues = []): bool {
		if (!isset($resultLine[$symbol])) {
			return true;
		}
		if (in_array($resultLine[$symbol], $skipValues, true)) {
			return true;
		}

		return false;
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
		$config .= 'timeInFilenameFormat = ' . $this->timeInFilenameFormat . "\n";
		$config .= 'timeZone = ' . $this->timeZone . "\n";
		$config .= 'onlyTimes = ' . $this->onlyTimes . "\n";
		$config .= 'skipValue = ' . $this->skipValue . "\n";
		$config .= 'conversionsFilename = ' . $this->conversionsFilename . "\n";
		$config .= 'propertyLevel = ' . $this->propertyLevel . "\n";
		$config .= 'propertySymbolColumn = ' . $this->propertySymbolColumn . "\n";
		$config .= 'propertyValueColumn = ' . $this->propertyValueColumn . "\n";


		foreach ($this->propertySymbolsToColumns as $property) {
			if ($this->propertyLevel === 'column') {
				$config .= 'properties[] = "' . self::serializePropertyConfiguration($property) . "\"\n";
			} else {
				$config .= 'properties[] = "' . $property['symbol'] . "\"\n";
			}
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
