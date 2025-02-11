<?php

namespace Environet\Sys\Upload;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use SimpleXMLElement;

/**
 * Class Statistics
 *
 * @package Environet\Sys\Upload
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class Statistics {

	/**
	 * @var int of uploaded properties
	 */
	protected int $inputPropertiesCount = 0;

	/**
	 * @var array Statistics of one property
	 */
	protected array $properties = [];

	/**
	 * @var string|null The input xml file
	 */
	protected ?string $inputXmlFile = null;

	/**
	 * @var string|null
	 */
	protected ?string $userId = null;

	/**
	 * @var string|null
	 */
	protected ?string $monitoringPointId = null;

	/**
	 * @var DateTime|null
	 */
	protected ?DateTime $date = null;

	/**
	 * @var array
	 */
	protected array $duplicatePointTimes = [];

	/**
	 * Array of message types, and messages
	 * @var array
	 */
	protected $messages;


	public function __construct() {
		$this->messages = [
			'info'    => [],
			'success' => [],
			'warning' => [],
			'error'   => [],
		];
	}


	/**
	 * Get count of input properties
	 *
	 * @return int
	 */
	public function getInputPropertiesCount(): int {
		return $this->inputPropertiesCount;
	}


	/**
	 * Set count of input properties
	 *
	 * @param int $inputPropertiesCount
	 *
	 * @return Statistics
	 */
	public function setInputPropertiesCount(int $inputPropertiesCount): Statistics {
		$this->inputPropertiesCount = $inputPropertiesCount;

		return $this;
	}


	/**
	 * Get count of values under a property
	 *
	 * @param string $symbol
	 *
	 * @return int
	 */
	public function getPropertyValuesCount(string $symbol): int {
		return $this->properties[$symbol]['count'] ?? 0;
	}


	/**
	 * Set count of values under a property
	 *
	 * @param string $symbol
	 * @param int    $propertyValuesCount
	 *
	 * @return Statistics
	 */
	public function setPropertyValuesCount(string $symbol, int $propertyValuesCount): Statistics {
		$this->addProperty($symbol);
		$this->properties[$symbol]['count'] = $propertyValuesCount;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function hasDuplicatePointTimes(): bool {
		return !empty(array_filter($this->duplicatePointTimes));
	}


	/**
	 * @param string $symbol
	 *
	 * @return array
	 */
	public function getDuplicatePointTimes(string $symbol): array {
		return $this->duplicatePointTimes[$symbol] ?? [];
	}


	/**
	 * @param string $symbol
	 * @param array  $duplicatePointTimes
	 *
	 * @return Statistics
	 */
	public function setDuplicatePointTimes(string $symbol, array $duplicatePointTimes): Statistics {
		$this->duplicatePointTimes[$symbol] = $duplicatePointTimes;

		return $this;
	}


	/**
	 * Add a property to statistics array, with zero values (if not set)
	 *
	 * @param string $symbol
	 *
	 * @return Statistics
	 */
	public function addProperty(string $symbol): Statistics {
		if (!array_key_exists($symbol, $this->properties)) {
			$this->properties[$symbol] = [
				'count'      => 0,
				'inserts'    => 0,
				'updates'    => 0,
				'no_changes' => 0,
				'min_time'   => null,
				'max_time'   => null
			];
		}

		return $this;
	}


	/**
	 * Get symbols of properties
	 *
	 * @return int[]|string[]
	 */
	public function getProperties(): array {
		return array_keys($this->properties);
	}


	/**
	 * Add a result to statistics, and check if it's already in results.
	 *
	 * @param string $symbol
	 * @param mixed  $value
	 * @param array  $selectData
	 *
	 * @return Statistics
	 */
	public function addResult(string $symbol, $value, array $selectData): Statistics {
		$this->addProperty($symbol);
		if (empty($selectData)) {
			//No existing data with time, it is an insert
			$this->properties[$symbol]['inserts'] ++;
		} else {
			$latest = reset($selectData);
			if ($latest && (float) $latest['value'] != (float) $value) {
				//Has an existing data, but the value is changed, it is an update
				$this->properties[$symbol]['updates'] ++;
			} else {
				//Has an existing data, but value is the same, it is unchanged
				$this->properties[$symbol]['no_changes'] ++;
			}
		}

		return $this;
	}


	/**
	 * Get inserts of a property
	 *
	 * @param string $symbol
	 *
	 * @return int
	 */
	public function getPropertyInserts(string $symbol): int {
		return $this->properties[$symbol]['inserts'] ?? 0;
	}


	/**
	 * Set inserts of a property
	 *
	 * @param string $symbol
	 * @param int    $value
	 *
	 * @return Statistics
	 */
	public function setPropertyInserts(string $symbol, int $value): Statistics {
		$this->addProperty($symbol);
		$this->properties[$symbol]['inserts'] = $value;

		return $this;
	}


	/**
	 * Get updates of a property
	 *
	 * @param string $symbol
	 *
	 * @return int
	 */
	public function getPropertyUpdates(string $symbol): int {
		return $this->properties[$symbol]['updates'] ?? 0;
	}


	/**
	 * Set updates of a property
	 *
	 * @param string $symbol
	 * @param int    $value
	 *
	 * @return Statistics
	 */
	public function setPropertyUpdates(string $symbol, int $value): Statistics {
		$this->addProperty($symbol);
		$this->properties[$symbol]['updates'] = $value;

		return $this;
	}


	/**
	 * Get no-changes of a property
	 *
	 * @param string $symbol
	 *
	 * @return int
	 */
	public function getPropertyNoChanges(string $symbol): int {
		return $this->properties[$symbol]['no_changes'] ?? 0;
	}


	/**
	 * Set no-changes of a property
	 *
	 * @param string $symbol
	 * @param int    $value
	 *
	 * @return Statistics
	 */
	public function setPropertyNoChanges(string $symbol, int $value): Statistics {
		$this->addProperty($symbol);
		$this->properties[$symbol]['no_changes'] = $value;

		return $this;
	}


	/**
	 * Get min time of a property
	 *
	 * @param string $symbol
	 *
	 * @return DateTime|null
	 */
	public function getPropertyMinTime(string $symbol): ?DateTime {
		return $this->properties[$symbol]['min_time'] ?? null;
	}


	/**
	 * Get min time of a property, with optional format, and timezone conversion
	 *
	 * @param string $symbol
	 *
	 * @return DateTime|null
	 */
	public function getPropertyMinTimeFormatted(string $symbol, string $format = 'c', ?string $timezone = null): ?string {
		$date = $this->getPropertyMinTime($symbol) ? (clone $this->getPropertyMinTime($symbol)) : null;
		if (!$date) {
			return null;
		}
		if ($timezone) {
			$date->setTimezone(new DateTimeZone($timezone));
		}

		return $date->format($format);
	}


	/**
	 * Get min time of a property to output in html. In case of UTC, or no specified timezone, it will return the time in UTC.
	 * If a custom timezone is specified, it will return the time in that timezone, with the UTC time in parentheses.
	 *
	 * @param string      $symbol
	 * @param string|null $customTimezone
	 *
	 * @return DateTime|null
	 */
	public function getPropertyMinTimeWithTimezones(string $symbol, ?string $customTimezone = null): ?string {
		if ($customTimezone === 'UTC' || $customTimezone === null) {
			return $this->getPropertyMinTimeFormatted($symbol, 'Y-m-d H:i:s') . ' UTC';
		} else {
			return $this->getPropertyMinTimeFormatted($symbol, 'Y-m-d H:i:s', $customTimezone) . ' ' . $customTimezone
				. ' (' . $this->getPropertyMinTimeFormatted($symbol, 'Y-m-d H:i:s') . ' UTC)';
		}
	}


	/**
	 * Set min time of a property
	 *
	 * @param string        $symbol
	 * @param DateTime|null $time
	 *
	 * @return Statistics
	 */
	public function setPropertyMinTime(string $symbol, ?DateTime $time): Statistics {
		$this->addProperty($symbol);
		$this->properties[$symbol]['min_time'] = $time;

		return $this;
	}


	/**
	 * Get max time of a property
	 *
	 * @param string $symbol
	 *
	 * @return DateTime|null
	 */
	public function getPropertyMaxTime(string $symbol): ?DateTime {
		return $this->properties[$symbol]['max_time'] ?? null;
	}


	/**
	 * Get min time of a property, with optional format, and timezone conversion
	 *
	 * @param string $symbol
	 *
	 * @return DateTime|null
	 */
	public function getPropertyMaxTimeFormatted(string $symbol, string $format = 'c', ?string $timezone = null): ?string {
		$date = $this->getPropertyMaxTime($symbol) ? (clone $this->getPropertyMaxTime($symbol)) : null;
		if (!$date) {
			return null;
		}
		if ($timezone) {
			$date->setTimezone(new DateTimeZone($timezone));
		}

		return $date->format($format);
	}


	/**
	 * Get max time of a property to output in html. In case of UTC, or no specified timezone, it will return the time in UTC.
	 * If a custom timezone is specified, it will return the time in that timezone, with the UTC time in parentheses.
	 *
	 * @param string      $symbol
	 * @param string|null $customTimezone
	 *
	 * @return DateTime|null
	 */
	public function getPropertyMaxTimeWithTimezones(string $symbol, ?string $customTimezone = null): ?string {
		if ($customTimezone === 'UTC' || $customTimezone === null) {
			return $this->getPropertyMaxTimeFormatted($symbol, 'Y-m-d H:i:s') . ' UTC';
		} else {
			return $this->getPropertyMaxTimeFormatted($symbol, 'Y-m-d H:i:s', $customTimezone) . ' ' . $customTimezone
				. ' (' . $this->getPropertyMaxTimeFormatted($symbol, 'Y-m-d H:i:s') . ' UTC)';
		}
	}


	/**
	 * Set max time of a property
	 *
	 * @param string        $symbol
	 * @param DateTime|null $time
	 *
	 * @return Statistics
	 */
	public function setPropertyMaxTime(string $symbol, ?DateTime $time): Statistics {
		$this->addProperty($symbol);
		$this->properties[$symbol]['max_time'] = $time;

		return $this;
	}


	/**
	 * Get input xml file
	 *
	 * @return string|null
	 */
	public function getInputXmlFile(): ?string {
		return $this->inputXmlFile;
	}


	/**
	 * Set input xml file
	 *
	 * @param string|null $inputXmlFile
	 *
	 * @return Statistics
	 */
	public function setInputXmlFile(?string $inputXmlFile): Statistics {
		$this->inputXmlFile = $inputXmlFile;

		return $this;
	}


	/**
	 * Get monitoring point ID
	 *
	 * @return string|null
	 */
	public function getMonitoringPointId(): ?string {
		return $this->monitoringPointId;
	}


	/**
	 * Set monitoring point ID
	 *
	 * @param string|null $monitoringPointId
	 *
	 * @return Statistics
	 */
	public function setMonitoringPointId(?string $monitoringPointId): Statistics {
		$this->monitoringPointId = $monitoringPointId;

		return $this;
	}


	/**
	 * Get user id
	 *
	 * @return string|null
	 */
	public function getUserId(): ?string {
		return $this->userId;
	}


	/**
	 * Set user id
	 *
	 * @param string|null $userId
	 *
	 * @return Statistics
	 */
	public function setUserId(?string $userId): Statistics {
		$this->userId = $userId;

		return $this;
	}


	/**
	 * @return DateTime|null
	 */
	public function getDate(): ?DateTime {
		return $this->date;
	}


	/**
	 * @param DateTime|null $date
	 *
	 * @return Statistics
	 */
	public function setDate(?DateTime $date): Statistics {
		$this->date = $date;

		return $this;
	}


	/**
	 * Get messages, or messages of a specific type
	 * @return array|array[]
	 */
	public function getMessages(string $type = null): array {
		if (isset($type)) {
			return $this->messages[$type] ?? [];
		}

		return $this->messages;
	}


	/**
	 * Add an info, success, warning or error message to the statistics
	 * @throws Exception
	 */
	public function addMessage(string $type, string $message): Statistics {
		if (!array_key_exists($type, $this->messages)) {
			throw new Exception('Invalid message type');
		}
		$this->messages[$type][] = $message;

		return $this;
	}


	/**
	 * Convert statistics to XML response
	 *
	 * @return SimpleXMLElement
	 * @throws Exception
	 */
	public function toXml(): SimpleXMLElement {
		// Create xml root
		$xmlNamespaces = 'xmlns:environet="environet"';
		$xmlHeader = '<?xml version="1.0" encoding="UTF-8"?><environet:UploadStatistics ' . $xmlNamespaces . '></environet:UploadStatistics>';
		$xml = new SimpleXMLElement($xmlHeader);


		$xml->addChild('InputPropertiesCount', $this->getInputPropertiesCount());
		$xml->addChild('Date', $this->getDate()->format('c'));
		$xml->addChild('MonitoringPointId', $this->getMonitoringPointId());

		foreach ($this->getProperties() as $property) {
			$xmlProperty = $xml->addChild('PropertyStatistics');
			$xmlProperty->addChild('Symbol', $property);
			$xmlProperty->addChild('ValuesCount', $this->getPropertyValuesCount($property));
			$dptProperty = $xmlProperty->addChild('DuplicatePointTimes');
			foreach (array_keys($this->getDuplicatePointTimes($property)) as $time) {
				$dptProperty->addChild('DuplicatePointTime', $time);
			}
			$xmlProperty->addChild('Inserts', $this->getPropertyInserts($property));
			$xmlProperty->addChild('Updates', $this->getPropertyUpdates($property));
			$xmlProperty->addChild('NoChanges', $this->getPropertyNoChanges($property));
			$xmlProperty->addChild('MinTime', $this->getPropertyMinTimeFormatted($property));
			$xmlProperty->addChild('MaxTime', $this->getPropertyMaxTimeFormatted($property));
		}

		//If is there any message, add it to the statistics response
		$messages = array_filter($this->getMessages());
		if (!empty($messages)) {
			$xmlMessages = $xml->addChild('Messages');
			foreach ($messages as $type => $typeMessages) {
				foreach ($typeMessages as $typeMessage) {
					$xmlMessage = $xmlMessages->addChild('Message', $typeMessage);
					$xmlMessage->addAttribute('environet:type', $type, 'environet');
				}
			}
		}

		return $xml;
	}


	/**
	 * Build statistics based on a response XML
	 *
	 * @param SimpleXMLElement $xml
	 *
	 * @return Statistics
	 */
	public static function fromXml(SimpleXMLElement $xml): Statistics {
		$statistics = new self();
		$properties = $xml->xpath('/environet:UploadStatistics/environet:PropertyStatistics');

		$statistics->setInputPropertiesCount((int) ($xml->xpath('/environet:UploadStatistics/environet:InputPropertiesCount')[0] ?? null));
		$statistics->setDate(createValidDate((string) $xml->xpath('/environet:UploadStatistics/environet:Date')[0] ?? null));
		$statistics->setMonitoringPointId((string) $xml->xpath('/environet:UploadStatistics/environet:MonitoringPointId')[0] ?? null);

		foreach ($properties as $property) {
			$symbol = (string) $property->xpath('environet:Symbol')[0] ?? null;
			if (!$symbol) {
				continue;
			}

			$minTime = $property->xpath('environet:MinTime')[0] ?? null;
			$maxTime = $property->xpath('environet:MaxTime')[0] ?? null;
			$statistics->addProperty($symbol);
			$statistics->setPropertyValuesCount($symbol, (int) $property->xpath('environet:ValuesCount')[0] ?? 0);

			$duplicatePointTimes = [];
			foreach ($property->xpath('environet:DuplicatePointTimes/environet:DuplicatePointTime') as $time) {
				$duplicatePointTimes[] = (string) $time;
			}
			$statistics->setDuplicatePointTimes($symbol, $duplicatePointTimes);

			$statistics->setPropertyInserts($symbol, (int) $property->xpath('environet:Inserts')[0] ?? 0);
			$statistics->setPropertyUpdates($symbol, (int) $property->xpath('environet:Updates')[0] ?? 0);
			$statistics->setPropertyNoChanges($symbol, (int) $property->xpath('environet:NoChanges')[0] ?? 0);
			$statistics->setPropertyMinTime($symbol, $minTime ? DateTime::createFromFormat(DateTimeInterface::ISO8601, $minTime) : null);
			$statistics->setPropertyMaxTime($symbol, $maxTime ? DateTime::createFromFormat(DateTimeInterface::ISO8601, $maxTime) : null);
		}

		return $statistics;
	}


	/**
	 * Get data for event logger
	 * @return array
	 */
	public function getLogData(): array {
		$data = [];

		$data['input_properties_count'] = $this->getInputPropertiesCount();
		$data['monitoring_point_id'] = $this->getMonitoringPointId();
		$data['user_id'] = $this->getUserId();
		$data['property_statistics'] = [];

		foreach ($this->getProperties() as $property) {
			$data['property_statistics'][] = [
				'symbol'       => $property,
				'values_count' => $this->getPropertyValuesCount($property),
				'inserts'      => $this->getPropertyInserts($property),
				'updates'      => $this->getPropertyUpdates($property),
				'no_changes'   => $this->getPropertyNoChanges($property),
				'min_time'     => $this->getPropertyMinTimeFormatted($property),
				'max_time'     => $this->getPropertyMaxTimeFormatted($property),
			];
		}

		return $data;
	}


}
