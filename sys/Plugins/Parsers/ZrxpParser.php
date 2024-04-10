<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace Environet\Sys\Plugins\Parsers;

use DateTime;
use DateTimeZone;
use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\ParserInterface;
use Environet\Sys\Plugins\PluginBuilder;
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
	 * @var mixed Cut leading zeros from mpoint id
	 */
	private bool $cutMpointLeadingZeros;

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
		$this->cutMpointLeadingZeros = $config['cutMpointLeadingZeros'] == 1;

		//Create a map of properties, keys are the symbol in db, values are the symbols on zrxp files
		if (!empty($config['properties'])) {
			foreach ($config['properties'] as $propMap) {
				$propMap = explode(';', $propMap);
				if (count($propMap) !== 2 && count($propMap) !== 4) {
					continue;
				}
				//Key by property name in file
				$this->propertyMap[$propMap[0]] = array_filter([
					'property'                => str_replace('____', ';', $propMap[1]),
					'additionalMetadataKey'   => isset($propMap[2]) ? str_replace('____', ';', $propMap[2]) : null,
					'additionalMetadataValue' => isset($propMap[3]) ? str_replace('____', ';', $propMap[3]) : null,
				], fn($item) => !is_null($item));
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
		$lines = preg_split('/\n/', $resource->getContents());

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
			try {
				$sectionResult = $this->processSection($sectionNum, $section['meta'], $section['values']);
				if (!is_null($sectionResult)) {
					$results[] = $sectionResult;
				}
			} catch (CreateInputXmlException $e) {
				Console::getInstance()->writeLog(sprintf('Parsing of section %s in file %s failed, response: %s', $sectionNum, $resource->getName(), $e->getMessage()), true, true);
				continue;
			}
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

		//Define mpoint id, and property name (based on CNR or CNAME)
		$mPointId = $metaData['SANR'];
		if ($this->cutMpointLeadingZeros) {
			$mPointId = preg_replace('/^0+(.*)$/', '$1', $mPointId);
		}
		$propertyNameZrxp = null;
		if (isset($metaData['CNR'])) {
			$propertyNameZrxp = strtoupper($metaData['CNR']);
		} elseif (isset($metaData['CNAME'])) {
			$propertyNameZrxp = strtoupper($metaData['CNAME']);
		}

		if (is_null($propertyNameZrxp)) {
			Console::getInstance()->writeLog('Property name not found in meta tags (not in CNR and not in CNAME)', true);

			return null;
		}

		$propertyNameDb = $this->findProperty($propertyNameZrxp, $metaData);
		//Find the db-symbol of property
		if (is_null($propertyNameDb)) {
			Console::getInstance()->writeLog(sprintf('Property mapping for property %s isn\'t found in configuration', $propertyNameZrxp), true);

			return null;
		}

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

		if (empty($propertyValues)) {
			return null;
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
		$requiredKeys = ['ZRXPVERSION', 'SANR', 'TZ', 'LAYOUT'];
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
		$tzMap = [
			'BST'  => 'UTC+1',
			'CEST' => 'UTC+2',
			'MESZ' => 'UTC+2',
			'CET'  => 'UTC+1',
			'MEZ'  => 'UTC+1',
			'EEST' => 'UTC+3',
			'OESZ' => 'UTC+3',
			'EET'  => 'UTC+2',
			'OEZ'  => 'UTC+2',
			'FET'  => 'UTC+3',
			'GET'  => 'UTC+4',
			'GMT'  => 'UTC+0',
			'IST'  => 'UTC+1',
			'KUYT' => 'UTC+4',
			'MSD'  => 'UTC+4',
			'MSK'  => 'UTC+3',
			'SAMT' => 'UTC+4',
			'TRT'  => 'UTC+3',
			'WEST' => 'UTC+1',
			'WESZ' => 'UTC+1',
			'WET'  => 'UTC+0',
			'WEZ'  => 'UTC+0'
		];
		if (array_key_exists(strtoupper($timezone), $tzMap)) {
			$timezone = $tzMap[$timezone];
		}
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
	public static function create(Console $console, PluginBuilder $builder): ParserInterface {
		$console->writeLine('');
		$console->writeLine('Configuring ZRXP parser', Console::COLOR_YELLOW);

		//Version
		do {
			$console->writeLine('Select the ZRXP version. Supported values are: ' . implode(',', self::$supportedVersions), Console::COLOR_YELLOW);
			$zrxpVersion = $console->ask('ZRXP version:');
		} while (!in_array($zrxpVersion, self::$supportedVersions));

		//Cut leading zeros
		$cutMpointLeadingZeros = $console->askYesNo('Do you want to cut leading zeros from monitoring point ids?', false);

		//Properties
		$console->writeLine('Configuration of properties', Console::COLOR_YELLOW);
		$console->writeLine('Name of observed properties in ZRXP files can be different as in the distribution node, so you have to create a map of properties.');
		$properties = [];
		do {
			$propertyConfig = [];
			$propertyConfig[] = $propDb = $console->ask("Property's symbol as it is defined on distribution node:");
			$propertyConfig[] = $propZrxp = $console->ask("Property's name in ZRXP file (value if CNR/CNAME property, the characters after 'CNR/CNAME' and before separator):");
			if ($console->askYesNo("Do you want to find match under other metadata to identify the property (e.g. interval in TSPATH)", false)) {
				$propertyConfig[] = $propAdditionalMetadataKey = $console->ask("Identifier of metadata (e.g. TSPATH):");
				$propertyConfig[] = $propAdditionalMetadataValue = $console->ask("Match string in the value of " . $propAdditionalMetadataKey . " (e.g. Precip/MDay.Total):");
			}

			$properties[] = implode(';', $propertyConfig);
		} while ($console->askYesNo('Do you want to add more properties?'));

		$config = [
			'zrxpVersion' => $zrxpVersion,
			'cutMpointLeadingZeros' => $cutMpointLeadingZeros,
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
		$config .= 'cutMpointLeadingZeros = ' . ($this->cutMpointLeadingZeros ? 1 : 0) . "\n";

		foreach ($this->propertyMap as $dbProp => $propertyConfig) {
			//Replace ; to ____ in values
			$dbProp = str_replace(';', '____', $dbProp);
			$propertyConfig = array_map(fn($configItem) => str_replace(';', '____', $configItem), $propertyConfig);

			$config .= 'properties[] = "' . $dbProp . ';' . implode(';', $propertyConfig) . "\"\n";
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


	/**
	 * Find a property in the mapping with or without addition metadata parsing
	 *
	 * @param string $propertyNameZrxp
	 * @param array  $metadata
	 *
	 * @return string|null
	 */
	protected function findProperty(string $propertyNameZrxp, array $metadata): ?string {
		$propertyNameZrxp = strtoupper($propertyNameZrxp);
		foreach ($this->propertyMap as $dbProp => $propertyConfig) {
			if (strtoupper($propertyConfig['property']) !== $propertyNameZrxp) {
				//Property name not matching, skip
				continue;
			}
			$mdKey = $propertyConfig['additionalMetadataKey'] ? strtoupper($propertyConfig['additionalMetadataKey']) : null;
			$mdValue = $propertyConfig['additionalMetadataValue'] ? strtoupper($propertyConfig['additionalMetadataValue']) : null;
			if (!empty($mdKey) && //Has additional metadata config
				!empty($mdValue) && //Additional metadata value not empty
				(empty($metadata[$mdKey]) || strpos(strtoupper($metadata[$mdKey]), $mdValue) === false) //Metadata value of file empty, or not matching with the pattern
			) {
				//Metadata not matched
				continue;
			}

			//Found a match
			return $dbProp;
		}

		return null;
	}


}
