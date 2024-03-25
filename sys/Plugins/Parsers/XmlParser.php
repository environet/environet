<?php

namespace Environet\Sys\Plugins\Parsers;

use DateTime;
use DateTimeZone;
use Environet\Sys\Commands\Console;
use Environet\Sys\General\Model\Configuration\Type\Parameters\AbstractFormatParameter;
use Environet\Sys\General\Model\Configuration\Type\Parameters\DateParameter;
use Environet\Sys\General\Model\Configuration\Type\Parameters\ObservedPropertySymbolParameter;
use Environet\Sys\General\Model\Configuration\Type\Parameters\ObservedPropertyValueParameter;
use Environet\Sys\General\Model\ResolvedGroup;
use Environet\Sys\General\Model\ResolvedItem;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\ParserInterface;
use Environet\Sys\Plugins\Resource;
use Environet\Sys\Xml\CreateInputXml;
use Environet\Sys\Xml\Exceptions\CreateInputXmlException;
use Environet\Sys\Xml\Model\InputXmlData;
use Environet\Sys\Xml\Model\InputXmlPropertyData;
use Exception;
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
	 * @var bool if true parser will process empty tags as missing value
	 */
	private $skipEmptyValueTag;

	/**
	 * @var mixed Value to ignore
	 */
	private $skipValue;

	/**
	 * @var string Separator for decimals.
	 */
	private $separatorDecimals;

	/**
	 * @var array<ResolvedGroup> Resolved groups are stored in this array while parsing
	 */
	private array $flatList = [];


	/**
	 * XMLParser constructor.
	 *
	 * @param array $config
	 *
	 * @throws Exception
	 */
	public function __construct(array $config) {

		$this->separatorThousands = $config['separatorThousands'];
		$this->separatorDecimals = $config['separatorDecimals'];
		$this->skipEmptyValueTag = isset($config['skipEmptyValueTag']) ? (bool) $config['skipEmptyValueTag'] : false;
		$this->skipValue = $config['skipValue'];

		parent::__construct($config);
	}


	/**
	 * Recursive function to parse a xml tree to acquire values for given parameters from xml tree
	 * List of information gathered from xml stored in $this->flatList
	 *
	 * @param SimpleXMLElement   $xml              Xml element to parse
	 * @param ResolvedGroup|null $parentGroup      Parent group in case of nested groups. This group can contain some already parsed parameters
	 * @param int                $hierarchyCounter Level of hierarchy, initially 0
	 * @param string|null        $parentPath       Parent common path in case of nested groups
	 * @param array|null         $parametersOnly   If set, only parameters in this array will be parsed - used for recursive calls
	 *
	 * @return void
	 * @throws Exception
	 */
	private function parseIntoHierarchy(
		SimpleXMLElement $xml,
		?ResolvedGroup $parentGroup = null,
		int $hierarchyCounter = 0,
		string $parentPath = null,
		array $parametersOnly = null
	): void {
		//Get formats configuration
		$formatsConfig = $this->getFormatsConfig();
		if ($hierarchyCounter > 10) {
			throw new Exception('XML hierarchy deeper than 10');
		}

		if (!$formatsConfig->count()) {
			//No parameters to parse
			Console::getInstance()->writeLog('Error condition 1: Call, but all information already resolved.', true);

			return;
		}

		//Get common elements from tag hierarchy, build the common path and the group xpath
		$commonElements = $this->getCommonElements($parentPath, $parametersOnly);
		if ($hierarchyCounter === 0 && empty($commonElements)) {
			throw new Exception('XML definition does not have a top-level element');
		}
		$commonPath = ($parentPath ? $parentPath . '/' : '') . implode('/', $commonElements);
		$groupXpath = implode('/', $hierarchyCounter === 0 ? array_slice($commonElements, 1) : $commonElements); //Root element is not part of group xpath

		//Get groups with group path
		$groups = $xml->xpath($groupXpath ?: '/');

		if ($groups == null) {
			throw new Exception('Given elements do not exist in file: ' . $groupXpath);
		}

		//Get iteration boundaries for the current tag
		$iterationsBoundaries = null;
		foreach ($formatsConfig->getParameters() as $parameter) {
			if ($parameter->isIterable()) {
				if ($iterationsBoundaries && $iterationsBoundaries !== $parameter->getIterationBoundaries()) {
					//Iteration boundaries can't be different for tags
					throw new Exception('Different iteration boundaries for the same tag');
				}
				$iterationsBoundaries = $parameter->getIterationBoundaries();
			}
		}
		$start = $iterationsBoundaries[0] ?? 1;
		$end = $iterationsBoundaries[1] ?? 1;

		for ($i = $start; $i <= $end; $i ++) {
			//Iterate over groups, parse parameters and recurse if needed
			foreach ($groups as $groupKey => $group) {
				//The group where the parameters will be stored can be a new group or a clone of the parent group in case of nested calls.
				//If nested, the parent group already contains some parameters, but we have to clone it because this sub-group can contain the same parameters with different values.
				$resolvedGroup = $parentGroup ? clone $parentGroup : new ResolvedGroup();
				$subParameters = []; //Collect sub-parameters for recursion

				foreach ($formatsConfig->getParameters() as $parameter) {
					if ($parametersOnly && !in_array($parameter, $parametersOnly, true)) {
						//In case of recursive call, only parse parameters in $parametersOnly (which are not yet resolved)
						continue;
					}

					//Get xpath under current group
					$xpath = $parameter->getXpath($commonPath, $i);

					/** @var SimpleXMLElement|SimpleXMLElement[] $targetElement */
					//Target element is the element to be parsed. It can be the group itself or a collection of sub-items of the group
					$targetElement = $xpath ? $group->xpath($xpath) : $group;

					if ($targetElement == null) {
						if ($parameter->isOptional()) {
							//Optional element is missing, skip group
							continue;
						}
						Console::getInstance()->writeLog(sprintf('Required element "%s" missing in group %d, skip group', $xpath, $groupKey + 1));
						continue 2;
					}
					if ($targetElement instanceof SimpleXMLElement && !empty($parameter->getAttribute()) && $targetElement->getName() === end($commonElements)) {
						//Desired information is attribute of group-defining tag
						$resolvedGroup->addItem(new ResolvedItem($parameter, $parameter->getXmlValue($targetElement, $this->skipEmptyValueTag)));
					} else {
						if (count($targetElement) === 1) {
							//Target element is a single element, value can be parsed
							$resolvedGroup->addItem(new ResolvedItem($parameter, $parameter->getXmlValue($targetElement[0], $this->skipEmptyValueTag)));
						} else {
							//Target element is a collection of elements, recursion is needed
							$subParameters[] = $parameter;
						}
					}
				}

				if ($subParameters) {
					//Do recursion for sub-parameters. It can create new groups or add parameters to the current group.
					//The new groups will be added to $this->flatList in the called nested function.
					$this->parseIntoHierarchy($group, $resolvedGroup, $hierarchyCounter + 1, $commonPath, $subParameters);
				} else {
					//All information available (from this group and from recursion), add to flatList
					$this->flatList[] = $resolvedGroup;
				}
			}
		}
	}


	/**
	 * Returns common elements from xml tag hierarchy
	 *
	 * @param string|null $underPath      If set, common elements are searched under this path
	 * @param array|null  $parametersOnly If set, only parameters in this array will be parsed
	 *
	 * @return array The common elements of tag hierarchy, if any
	 */
	private function getCommonElements(?string $underPath = null, array $parametersOnly = null): array {
		$underPath = $underPath ? explode('/', trim($underPath, '/')) : [];

		//Collect parameters where the common elements are searched. If $parametersOnly is set, only these parameters are considered.
		$parameters = array_values(array_filter(
			$this->formatsConfig->getParameters(),
			fn(AbstractFormatParameter $parameter) => !$parametersOnly || in_array($parameter, $parametersOnly, true)
		));

		//Get tag hierarchies for each parameter, remove elements of $underPath
		$tagHierarchies = array_map(
			fn(AbstractFormatParameter $parameter) => array_values(array_diff($parameter->getTagHierarchy(), $underPath)),
			$parameters
		);

		//Collect common elements from tag hierarchies. Iterate while all parameter's hierarchy element is the same
		$i = 0;
		$commonElements = [];
		while (true) {
			$elements = array_map(fn(array $hierarchy) => $hierarchy[$i], $tagHierarchies);
			$elements = array_values(array_unique(array_filter($elements, fn($element) => $element !== null)));
			if (count($elements) === 1) {
				$commonElements[] = $elements[0];
				$i ++;
				continue;
			}
			break;
		}

		return $commonElements;
	}


	/**
	 * Convert value parameters in group: Remove thousands separator, change decimal separator to ".", convert value to float and convert to unit if needed by valueConversion parameter.
	 *
	 * @param ResolvedGroup $resolvedGroup
	 *
	 * @return bool returns true, if value is valid. Value may be empty string if not available. In this case value is invalid.
	 */
	private function convertValue(ResolvedGroup $resolvedGroup): bool {
		$skipValues = [''];
		if ($this->skipValue) {
			$skipValues[] = $this->skipValue;
		}

		foreach ($resolvedGroup->getItemsWithParameter(ObservedPropertyValueParameter::class) as $valueItem) {
			if (in_array($valueItem->getValue(), $skipValues, true)) {
				return false;
			}
			if ($this->separatorThousands != '') {
				$valueItem->setValue(str_replace($this->separatorThousands, '', $valueItem->getValue()));
			}
			if ($this->separatorDecimals != '.' && $this->separatorDecimals != '') {
				$valueItem->setValue(str_replace($this->separatorDecimals, '.', $valueItem->getValue()));
			}
			if ($valueItem->getParameter()->getValueConversion()) {
				//Convert value if valueConversion parameter is set in format configuration
				$valueItem->setValue($valueItem->getParameter()->convertValueUnit($valueItem->getValue()));
			}
			$valueItem->setValue((float) $valueItem->getValue());
		}

		return true;
	}


	/**
	 * Assembles date from components like day, month, year, hour and minute. If these components are present in resolved group, they are joined to a "DateTime" item and deleted from $entry.
	 * The "DateTime" item has time format as given by API_TIME_FORMAT_STRING
	 *
	 * @param ResolvedGroup $resolvedGroup
	 *
	 * @throws Exception
	 */
	private function assembleDate(ResolvedGroup $resolvedGroup) {
		//Get all date-type items from resolved group
		$dateItems = $resolvedGroup->getItemsWithParameter(DateParameter::class);
		$ncd = $resolvedGroup->getMonitoringPointItem()->getValue();

		//Build an initial date array with default values. These values will be overwritten by the date components from.
		$dateParams = ['Year' => null, 'Month' => null, 'Day' => null, 'Hour' => '00', 'Minute' => '00', 'Second' => '00'];
		foreach ($dateItems as $dateItem) {
			$format = $dateItem->getParameter()->getFormat();
			$type = $dateItem->getParameter()->getDateType();
			$value = $dateItem->getValue();

			if (in_array($type, ['Year', 'Month', 'Day', 'Hour', 'Minute', 'Second'])) {
				//Atomic date components are stored in $dateParams
				$dateParams[$type] = $value;
			}
			if (strpos($type, 'Date') !== false) { //In case of Date or DateTime
				//In case of Date or DateTime, the date components are stored in $dateParams
				$date = DateTime::createFromFormat($format, $value);
				if (!$date) {
					throw new Exception(sprintf("Invalid date format (monitoring point national code: %s): Format is %s, value is %s. Entry dropped.", $ncd, $format, $value));
				}
				$dateParams['Year'] = preg_match('/[yY]/', $format) ? $date->format('Y') : '0001';
				$dateParams['Month'] = preg_match('/[mMnF]/', $format) ? $date->format('m') : '01';
				$dateParams['Day'] = preg_match('/[dD]/', $format) ? $date->format('d') : '01';
			}
			if (strpos($type, 'Time') !== false) { //In case of Time or DateTime
				//In case of Time or DateTime, the time components are stored in $dateParams
				$time = DateTime::createFromFormat($format, $value);
				if (!$time) {
					throw new Exception(sprintf("Invalid time format (monitoring point national code: %s): Format is %s, value is %s. Entry dropped.", $ncd, $format, $value));
				}
				$dateParams['Hour'] = preg_match('/[GHh]/', $format) ? $time->format('H') : '00';
				$dateParams['Minute'] = preg_match('/[i]/', $format) ? $time->format('i') : '00';
				$dateParams['Second'] = preg_match('/[s]/', $format) ? $time->format('s') : '00';
			}
			$resolvedGroup->removeItem($dateItem);
		}

		//Build DateTime item from dateParams
		$date = DateTime::createFromFormat('Y-m-d-H-i-s', implode('-', $dateParams), $this->getTimeZone());
		if (!$date) {
			throw new Exception(sprintf("Invalid date or time (monitoring point national code: %s): %s - Entry dropped", $ncd, implode('-', $dateParams)));
		}
		//Convert date to UTC
		$date->setTimezone(new DateTimeZone('UTC'));

		//Add DateTime item to resolved group with API_TIME_FORMAT_STRING format
		$resolvedGroup->addItem(new ResolvedItem(
			(new DateParameter())->setOptions(['Type' => 'DateTime', 'Format' => self::API_TIME_FORMAT_STRING]),
			$date->format(self::API_TIME_FORMAT_STRING)
		));
	}


	/**
	 * Parse XML file and return the parsed data
	 *
	 * @inheritDoc
	 * @throws CreateInputXmlException
	 * @throws Exception
	 */
	public function parse(Resource $resource): array {
		$this->flatList = [];
		Console::getInstance()->writeLog(sprintf('Received %s characters', strlen($resource->getContents())));

		$resource->setContents(str_replace('xlink:href', 'href', $resource->getContents())); // Workaround for WaterML 2.0

		libxml_use_internal_errors(true); // this turns off spitting parsing errors on screen
		$xml = new SimpleXMLElement($resource->getContents());
		if (!empty($ns = $xml->getDocNamespaces())) {
			$xml->registerXPathNamespace('def', array_values($ns)[0]);
		}

		//Check if formats are defined
		if (!$this->getFormatsConfig()->count()) {
			throw new Exception('No formats defined for XML parser');
		}

		//Parse XML into hierarchy, store in $this->flatList.
		//This list contains resolved groups, each group can contain one MonitoringPoint, one ObservedPropertySymbol, multiple Date and multiple ObservedPropertyValue items
		$this->parseIntoHierarchy($xml);

		foreach ($this->flatList as $key => $resolvedGroup) {
			//Remove groups without value
			$valueItems = $resolvedGroup->getItemsWithParameter(ObservedPropertyValueParameter::class);
			if (count($valueItems) < 1) {
				continue;
			}

			//Split groups with multiple value items to multiple groups with one value item. The original group is deleted from $this->flatList
			foreach ($valueItems as $valueItem) {
				//Create new group with the same parameter.
				$newGroup = new ResolvedGroup();
				$newGroup->addItem(new ResolvedItem($resolvedGroup->getMonitoringPointItem()->getParameter(), $resolvedGroup->getMonitoringPointItem()->getValue()));

				//Add observed property symbol to new group
				if ($resolvedGroup->getObservedPropertySymbolItem()) {
					//The property symbol is defined in the resolved group. Copy this item to the new group.
					$newGroup->addItem(new ResolvedItem(
						$resolvedGroup->getObservedPropertySymbolItem()->getParameter(),
						$resolvedGroup->getObservedPropertySymbolItem()->getValue()
					));
				} elseif ($valueItem->getParameter()->getSymbol()) {
					//The property symbol is not defined in the resolved group, but it is defined in the value item (by the configuration). Add this symbol to the new group.
					$newGroup->addItem(new ResolvedItem(new ObservedPropertySymbolParameter(), $valueItem->getParameter()->getSymbol()));
				}

				//Copy date items to new group
				foreach ($resolvedGroup->getDateItems() as $dateItem) {
					$newGroup->addItem(new ResolvedItem($dateItem->getParameter(), $dateItem->getValue()));
				}

				//Add new value item to new group, and add new group to $this->flatList
				$newGroup->addItem($valueItem);
				$this->flatList[] = $newGroup;
			}

			unset($this->flatList[$key]);
		}

		$this->flatList = array_values($this->flatList);

		//Replace external observed property symbols and add missing information from API-Call (Monitoring Point or Observed Property Symbol)
		foreach ($this->flatList as $key => $resolvedGroup) {
			if ($resource->getSpecificPointNCD() && !$resolvedGroup->getMonitoringPointItem()) {
				//Add monitoring point national code from Resource, if it's not present in resolved item but was defined in the transport layer.
				$resolvedGroup->addItem(new ResolvedItem(
					$this->getFormatsConfig()->getMonitoringPointParameter(),
					$resource->getSpecificPointNCD()
				));
			}

			if (($symbolItem = $resolvedGroup->getObservedPropertySymbolItem())) {
				//Convert external (in-file) symbol to internal symbol based on opserverd property symbol mapping
				/** @var ObservedPropertySymbolParameter $configParameter */
				$configParameter = $symbolItem->getParameter();
				$symbolNameInFile = $symbolItem->getValue();
				$variableName = $configParameter->getVariable();
				if ($symbolNameInFile && $variableName) {
					if (($symbol = $configParameter->mapObservedPropertySymbol($resource, $symbolNameInFile))) {
						$symbolItem->setValue($symbol);
					} else {
						// Delete whole resolved item as observed property was not found
						unset($this->flatList[$key]);
						continue;
					}
				}
			} elseif ($resource->getSpecificPropertySymbol()) {
				//If Observed Property Symbol is defined in the Resource (by the transport layer), set it in the resolved group

				//delete all occurrences of flat list with symbol different from the one in Resource
				$symbolItem = $resolvedGroup->getObservedPropertySymbolItem();
				if ($symbolItem && $symbolItem->getValue() != $resource->getSpecificPropertySymbol()) {
					unset($this->flatList[$key]);
					continue;
				}

				//Add property symbol from Resource, it is not present in resolved item
				$resolvedGroup->addItem(new ResolvedItem(
					$this->getFormatsConfig()->getPropertySymbolParameter(),
					$resource->getSpecificPropertySymbol()
				));
			}
		}
		$this->flatList = array_values($this->flatList);

		//Delete entries which do not fit to Resource meta (extra monitoring points, extra observed properties)
		if (!$resource->isKeepExtraData()) {
			foreach ($this->flatList as $key => $resolvedGroup) {
				if ($resource->getPointNCDs() && !in_array($resolvedGroup->getMonitoringPointItem()->getValue(), $resource->getPointNCDs())) {
					unset($this->flatList[$key]);
				} elseif ($resource->getPropertySymbols() && !in_array($resolvedGroup->getObservedPropertySymbolItem()->getValue(), $resource->getPropertySymbols())) {
					unset($this->flatList[$key]);
				}
			}
			$this->flatList = array_values($this->flatList);
		}

		//Assemble dates from components if needed
		foreach ($this->flatList as $key => $resolvedGroup) {
			try {
				$this->assembleDate($resolvedGroup);
			} catch (\Exception $e) {
				unset($this->flatList[$key]);
			}
		}
		$this->flatList = array_values($this->flatList);

		//Convert values
		foreach ($this->flatList as $key => $resolvedGroup) {
			if (!$this->convertValue($resolvedGroup)) {
				unset($this->flatList[$key]);
			}
		}
		$this->flatList = array_values($this->flatList);

		//Build result array, grouped by monitoring point and observed property symbol
		$resultArray = [];
		foreach ($this->flatList as $resolvedGroup) {
			$resultArray[$resolvedGroup->getMonitoringPointItem()->getValue()][$resolvedGroup->getObservedPropertySymbolItem()->getValue()][] = [
				'time'  => $resolvedGroup->getDateItems()[0]->getValue(),
				'value' => strval($resolvedGroup->getItemsWithParameter(ObservedPropertyValueParameter::class)[0]->getValue())
			];
		}

		$payloads = [];
		$creator = new CreateInputXml();
		foreach ($resultArray as $mPointId => $properties) {
			$properties = array_map(function ($key, $value) {
				return new InputXmlPropertyData($key, $value);
			}, array_keys($properties), $properties);
			$payloads[] = $creator->generateXml(new InputXmlData($mPointId, $properties));
		}

		return $payloads;
	}


	/**
	 * @inheritDoc
	 * @throws Exception
	 * @uses \Environet\Sys\Plugins\Parsers\CsvParser::serializePropertyConfiguration()
	 */
	public static function create(Console $console): ParserInterface {
		$console->writeLine('');
		$console->writeLine('Configuring XML parser', Console::COLOR_YELLOW);

		$monitoringPointType = self::createMonitoringPointTypeConfig($console);
		$timeZone = self::createTimeZoneConfig($console);

		$separatorThousands = $console->ask('Separator for groups of thousands in values. May be empty. Example: , for 12,040.01 cm');
		$separatorDecimals = $console->ask('Separator for decimals. Example: . for 142.3 cm');
		$formatsFilename = $console->ask('Filename for xml format definitions');

		$skipEmptyValueTag = $console->askWithDefault('Should parser ignore empty values in XML?', 'n');
		$skipEmptyValueTag = trim(strtolower($skipEmptyValueTag)) === 'y';

		$console->writeLine('Skip values with exact values: (if the value of a property matches the entered value, the row will be ignored)', Console::COLOR_YELLOW);
		$skipValue = $console->ask('Skip value:');

		$config = [
			'separatorThousands'  => $separatorThousands,
			'separatorDecimals'   => $separatorDecimals,
			'formatsFilename'     => $formatsFilename,
			'skipEmptyValueTag'   => $skipEmptyValueTag,
			'skipValue'           => $skipValue,
			'timeZone'            => $timeZone,
			'monitoringPointType' => $monitoringPointType ?: null
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
		$config .= 'skipEmptyValueTag = ' . $this->skipEmptyValueTag ? 1 : 0 . "\n";
		$config .= 'skipValue = ' . $this->skipValue . "\n";
		$config .= 'timeZone = ' . $this->timeZone . "\n";
		$config .= 'monitoringPointType = "' . $this->monitoringPointType . '"' . "\n";

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
