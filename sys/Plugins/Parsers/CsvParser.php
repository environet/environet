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
 * Class JsonParser
 *
 * Parser layer for CSV files
 *
 * @package Environet\Sys\Plugins\Parsers
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class CsvParser implements ParserInterface, BuilderLayerInterface {

	const API_TIME_FORMAT_STRING = 'Y-m-d\TH:i:sP';

	private $csvDelimiter;

	private $mPointIdCol;

	private $timeCol;

	private $timeFormat;

	private $properties;


	/**
	 * @inheritDoc
	 * @throws CreateInputXmlException
	 */
	public function parse(string $data): array {
		$dataArray = $this->mPointDataArrayFromCSV($data);
		return $this->meteringPointInputXmlsFromArray($dataArray);
	}


	// This will be an associative array: [mpointId => [propertySymbol => results]]
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

			//initialize time series for properties with an empty array
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
							'time' => $resultLine['time'],
							'value' => $resultLine[$property['symbol']]
						]
					]
				);
			}
		}

		return $resultArray;
	}


	/**
	 * @param array $mPointsArray
	 * @return SimpleXMLElement[]
	 * @throws CreateInputXmlException
	 */
	private function meteringPointInputXmlsFromArray(array $mPointsArray): array {
		$payloads = [];

		$creator = new CreateInputXml();
		foreach ($mPointsArray as $mPointId => $properties) {
			array_push($payloads, $creator->generateXml(new InputXmlData($mPointId, $this->inputXmlDataFromArray($properties))));
		}

		return $payloads;
	}


	private function inputXmlDataFromArray(array $propertiesArray) {
		return array_map(function ($key, $value) {
			return new InputXmlPropertyData($key, $value);
		}, array_keys($propertiesArray), $propertiesArray);
	}


	private function parseResultLine($line): array {
		$values = explode($this->csvDelimiter, $line);
		if (!$values[$this->timeCol]) {
			return [];
		}

		$data = [
			'mPointId' => $values[$this->mPointIdCol],
			'time' => DateTime::createFromFormat($this->timeFormat, $values[$this->timeCol])->format(self::API_TIME_FORMAT_STRING),
		];

		foreach ($this->properties as $property) {
			$data[$property['symbol']] = $values[$property['column']];
		}


		return $data;
	}


	/**
	 * MPointPropertyXmlInputGenerator constructor.
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
	 */
	public static function create(Console $console): ParserInterface {
		$console->writeLine('');
		$console->writeLine("Configuring csv parser");
		$csvDelimiter = $console->ask("Csv delimiter:", 1);
		$mPointIdCol = $console->ask("Monitoring point id column number:", 3) - 1;
		$timeCol = $console->ask("Time column number:", 3) - 1;
		$timeFormat = $console->ask("Time format (e.g. Y-m-d H:i:s):", 20);

		$console->writeLine("Configuring properties", Console::COLOR_YELLOW);

		$properties = [];
		do {
			$properties[] = self::serializePropertyConfiguration([
				'symbol' => $console->ask("Property symbol:", 30),
				'column' => $console->ask("Value column number:", 3)
			]);
		} while ($console->askYesNo('Do you want to add more properties?'));


		$config = [
			'csvDelimiter' => $csvDelimiter,
			'mPointIdCol' => $mPointIdCol,
			'timeCol' => $timeCol,
			'timeFormat' => $timeFormat,
			'properties' => $properties
		];

		return new self($config);
	}


	public static function serializePropertyConfiguration($property): string {
		return $property['symbol'] . ';' . $property['column'];
	}


	/**
	 * @inheritDoc
	 */
	public function serializeConfiguration(): string {
		$config = '';
		$config .= 'csvDelimiter = "' . $this->csvDelimiter . "\"\n";
		$config .= 'mPointIdCol = ' . $this->mPointIdCol . "\n";
		$config .= 'timeCol = ' . $this->timeCol . "\n";
		$config .= 'timeFormat = ' . $this->timeFormat . "\n";

		foreach ($this->properties as $property) {
			$config .= 'properties[] = "' .  self::serializePropertyConfiguration($property) . "\"\n";
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
