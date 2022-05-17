<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace Environet\Sys\Plugins\Parsers;

use DateTime;
use DateTimeZone;
use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\ParserInterface;
use Environet\Sys\Plugins\Resource;
use Environet\Sys\Xml\CreateInputXml;
use Environet\Sys\Xml\Exceptions\CreateInputXmlException;
use Environet\Sys\Xml\Model\InputXmlData;
use Environet\Sys\Xml\Model\InputXmlPropertyData;
use SimpleXMLElement;

/**
 * Class ZrxpParser
 *
 * Parser layer for ZRXP files
 *
 * @package Environet\Sys\Plugins\Parsers
 * @author  SRG Group <dev@srg.hu>
 */
class ZrxpParser extends AbstractParser implements BuilderLayerInterface {

	/**
	 * @var mixed Delimiter character
	 */
	private int $zrxpVersion;

	/**
	 * @var array
	 */
	private array $propertyMap = [];

	/**
	 * @var int[]
	 */
	private static array $supportedVersions = [2, 3];

	/**
	 * List of valid metadata keywords
	 *
	 * @var array|string[]
	 */
	private static array $metaKeywords = [
		'SANR',
		'SNAME',
		'SWATER',
		'CDASA',
		'CDASANAME',
		'CCHANNEL',
		'CCHANNELNO',
		'CMW',
		'CNAME',
		'CNR',
		'CUNIT',
		'REXCHANGE',
		'RINVAL',
		'RTIMELVL',
		'XVLID',
		'TSPATH',
		'CTAG',
		'CTAGKEY',
		'XTRUNCATE',
		'METCODE',
		'METERNUMBER',
		'EDIS',
		'TZ',
		'ZDATE',
		'ZRXPVERSION',
		'ZRXPCREATOR',
		'LAYOUT',
		'TASKID',
		'SOURCESYSTEM',
		'SOURCEID'
	];


	/**
	 * CsvParser constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {
		$this->zrxpVersion = $config['zrxpVersion'];

		//Create a map of properties, keys are the symbol in db, values are the symbols on zrxp files
		if (!empty($config['properties'])) {
			foreach ($config['properties'] as $propMap) {
				$propMap = explode(';', $propMap);
				if (count($propMap) !== 2) {
					continue;
				}
				$this->propertyMap[$propMap[0]] = strtoupper($propMap[1]);
			}
		}
		parent::__construct($config);
	}


	/**
	 * @inheritDoc
	 * @throws CreateInputXmlException
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::mPointDataArrayFromCSV()
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::meteringPointInputXmlsFromArray()
	 */
	public function parse(Resource $resource): array {
		$lines = preg_split('/\n/', $resource->contents);

		//Parse sections from a ZRXP file, create an array item for each section with metadata lines, and value lines
		$sections = [];
		$sectionNum = 0;
		$sectionPointer = null;
		$prevLineMeta = false;
		foreach ($lines as $line) {
			//Check if line is meta, if starts with #
			$isMeta = preg_match('/^#/', $line) > 0;
			if ($isMeta && !$prevLineMeta) {
				//Line is meta, but previous line wasn't a meta-line, so start a new section, create pointer of new section
				$sectionNum ++;
				$sections[$sectionNum] = ['meta' => [], 'values' => []];
				$sectionPointer = &$sections[$sectionNum];
			}
			$prevLineMeta = $isMeta;
			if (!is_null($sectionPointer)) {
				//If a section is started, add line to the section
				$sectionPointer[$isMeta ? 'meta' : 'values'][] = $line;
			}
		}

		//Build results for each section
		$results = [];
		foreach ($sections as $sectionNum => $section) {
			$results[] = $this->processSection($sectionNum, $section['meta'], $section['values']);
		}

		return $results;
	}


	/**
	 * Build XML result for each section in a ZRXP file
	 *
	 * @param int   $sectionNum
	 * @param array $meta
	 * @param array $values
	 *
	 * @return SimpleXMLElement
	 * @throws CreateInputXmlException
	 */
	protected function processSection(int $sectionNum, array $meta, array $values): ?SimpleXMLElement {
		//Build key=>value metadata based on meta lines
		$metaData = [];
		foreach ($meta as $metaLine) {
			$metaLine = preg_replace('/^#(.*)$/', '$1', $metaLine); //Cut # from the beginning of the line
			if (($metaLineParts = preg_split('/[|;]\*[|;]/', $metaLine))) { //Split it by separator
				foreach ($metaLineParts as $metaLinePart) {
					//Parse keywords and values
					if (preg_match('/^(' . implode('|', self::$metaKeywords) . ')(.*)$/', $metaLinePart, $m)) {
						$metaData[$m[1]] = $m[2];
					}
				}
			}
		}

		//Check if every required metadata is set, abort section if not found
		if (!empty(($missingKeys = $this->checkRequiredMetadataKeys($metaData)))) {
			Console::getInstance()->writeLog(sprintf('Some required metadata keys are missing in section %d: %s', $sectionNum, implode(',', $missingKeys)), true);

			return null;
		}

		//Check ZRXP version, abort section if invalid
		$version = (int) substr($metaData['ZRXPVERSION'], 0, 1);
		if ($version !== $this->zrxpVersion) {
			Console::getInstance()->writeLog(sprintf('Version %s is not supported under section %d', $version, $sectionNum), true);

			return null;
		}

		//Parse layout definition, and define timestamp and value columns
		$layout = preg_replace('/^\((.*)\)$/', '$1', $metaData['LAYOUT']);
		$layout = explode(',', $layout);
		$layoutColCount = count($layout);
		if (!in_array('timestamp', $layout) || !in_array('value', $layout)) {
			Console::getInstance()->writeLog('Timestamp and value layout definition is required', true);

			return null;
		}
		$valueCol = array_flip($layout)['value'];
		$timestampCol = array_flip($layout)['timestamp'];

		//Define skip value based on RINVAL property
		$skipValue = null;
		if (array_key_exists('RINVAL', $metaData)) {
			$skipValue = $metaData['RINVAL'];
		}

		//Parse timezone
		$timezone = $this->parseTimezone($metaData['TZ']);

		//Define mpoint id, and property name
		$mPointId = $metaData['SANR'];
		$propertyNameZrxp = strtoupper($metaData['CNR']);

		//Find the db-symbol of property
		if (!array_key_exists($propertyNameZrxp, array_flip($this->propertyMap))) {
			Console::getInstance()->writeLog(sprintf('Property mapping for property %s isn\'t found in configuration', $propertyNameZrxp), true);

			return null;
		}
		$propertyNameDb = array_flip($this->propertyMap)[$propertyNameZrxp];

		//Iterate over value lines, and parse the values and times
		$propertyValues = [];
		foreach ($values as $valueRow) {
			$valueRow = explode(' ', preg_replace('/\s+/', ' ', trim($valueRow)));
			if (count($valueRow) !== $layoutColCount) {
				//Value row must be built from the same amount of items which is defined in layout
				continue;
			}

			//Get timestamp and value from row
			$timestamp = $valueRow[$timestampCol] ?? null;
			$value = $valueRow[$valueCol] ?? null;

			if ($value === $skipValue) {
				//Skip value matched
				continue;
			}

			//Convert date to UTC
			$date = new DateTime($timestamp, $timezone);
			$date->setTimezone(new DateTimeZone('UTC'));

			//Add value item
			$propertyValues[] = [
				'time'  => $date->format('Y-m-d\TH:i:sP'),
				'value' => $value
			];
		}

		//Build XML
		return (new CreateInputXml())->generateXml(new InputXmlData($mPointId, [new InputXmlPropertyData($propertyNameDb, $propertyValues)]));
	}


	/**
	 * Check if every required keys are existing in metadata
	 *
	 * @param array $metaData
	 *
	 * @return array
	 */
	protected function checkRequiredMetadataKeys(array $metaData): array {
		$requiredKeys = ['ZRXPVERSION', 'SANR', 'CNR', 'TZ', 'LAYOUT'];
		$missingKeys = [];
		foreach ($requiredKeys as $requiredKey) {
			if (!array_key_exists($requiredKey, $metaData)) {
				$missingKeys[] = $requiredKey;
			}
		}

		return $missingKeys;
	}


	/**
	 * Parse PHP timezone
	 *
	 * @param string $timezone
	 *
	 * @return DateTimeZone
	 */
	protected function parseTimezone(string $timezone): DateTimeZone {
		if (preg_match('/^UTC([+\-]\d+)$/', $timezone, $m)) {
			return (new DateTimeZone('GMT' . $m[1]));
		}

		return (new DateTimeZone('UTC'));
	}


	/**
	 * Create plugin configuration
	 *
	 * @param Console $console
	 *
	 * @return ParserInterface
	 */
	public static function create(Console $console): ParserInterface {
		$console->writeLine('');
		$console->writeLine('Configuring ZRXP parser', Console::COLOR_YELLOW);

		//Version
		do {
			$console->writeLine('Select the ZRXP version. Supported values are: ' . implode(',', self::$supportedVersions), Console::COLOR_YELLOW);
			$zrxpVersion = $console->ask('ZRXP version:');
		} while (!in_array($zrxpVersion, self::$supportedVersions));

		//Properties
		$console->writeLine('Configuration of properties', Console::COLOR_YELLOW);
		$console->writeLine('Name of observed properties in ZRXP files can be different as in the distribution node, so you have to create a map of properties.');
		$properties = [];
		do {
			$propDb = $console->ask("Property's symbol as it is defined on distribution node:");
			$propZrxp = $console->ask("Property's name in ZRXP file (value if CNR property, the characters after 'CNR' and before separator):");
			$properties[] = $propDb . ';' . $propZrxp;
		} while ($console->askYesNo('Do you want to add more properties?'));

		$config = [
			'zrxpVersion' => $zrxpVersion,
			'properties'  => $properties
		];

		return new self($config);
	}


	/**
	 * @inheritDoc
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::serializePropertyConfiguration()
	 */
	public function serializeConfiguration(): string {
		$config = '';
		$config .= 'zrxpVersion = ' . $this->zrxpVersion . "\n";

		foreach ($this->propertyMap as $dbProp => $zrxpProp) {
			$config .= 'properties[] = "' . $dbProp . ';' . $zrxpProp . "\"\n";
		}

		return $config;
	}


	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return 'zrxp parser';
	}


	/**
	 * @inheritDoc
	 */
	public static function getHelp(): string {
		return 'For parsing data in ZRXP format.';
	}


}
