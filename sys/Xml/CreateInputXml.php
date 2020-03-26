<?php

namespace Environet\Sys\Xml;

use DateTime;
use Environet\Sys\Xml\Exceptions\CreateInputXmlException;
use Environet\Sys\Xml\Model\InputXmlData;
use SimpleXMLElement;

/**
 * Class CreateInputXml
 *
 * Create XML which will be pushed to the upload api. It has a custom (environet) schema, with a minimum data set for upload
 *
 * @package Sys\Xml
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class CreateInputXml {


	/**
	 * Generate Xml based on xml data object
	 *
	 * @param InputXmlData $inputXmlData
	 *
	 * @return SimpleXMLElement
	 * @throws CreateInputXmlException
	 */
	public function generateXml(InputXmlData $inputXmlData): SimpleXMLElement {
		if (!($inputXmlData->getPointId())) {
			//Point data is required
			throw new CreateInputXmlException('Property #$key: Point id is required');
		}

		//Validate properties
		foreach ($inputXmlData->getProperties() as $propertyKey => $property) {
			if (!($property->getPropertySymbol())) {
				//Property symbol not set for property
				throw new CreateInputXmlException("Property #$propertyKey: Property symbol is required");
			}
			//Validate array of values
			$this->validateValues($property->getValues(), $propertyKey);
		}

		//Create XML root
		$xmlNamespaces = 'xmlns:environet="environet"';
		$xmlHeader = '<?xml version="1.0" encoding="UTF-8"?><environet:UploadData '.$xmlNamespaces.'></environet:UploadData>';
		$xml = new SimpleXMLElement($xmlHeader);

		//Add the monitoring point id
		$xml->addChild('MonitoringPointId', $inputXmlData->getPointId());

		//Add properties and time series elements
		foreach ($inputXmlData->getProperties() as $propertyKey => $property) {
			//Add property, and propertyID
			$xmlProperty = $xml->addChild('Property');
			$xmlProperty->addChild('PropertyId', $property->getPropertySymbol());

			//Add time series and values with times
			$xmlTimeSeries = $xmlProperty->addChild('TimeSeries');
			foreach ($property->getValues() as $valueKey => $value) {
				$xmlPoint = $xmlTimeSeries->addChild('Point');
				$xmlPoint->addChild('environet:PointTime', $value['time']);
				$xmlPoint->addChild('environet:PointValue', $value['value']);
			}
		}

		return $xml;
	}


	/**
	 * Check if values array is valid.
	 * It must be an array with array items, and each sub-array must have a well-formatted value and time keys.
	 *
	 * @param array|array[] $values
	 * @param int   $propertyKey
	 *
	 * @return bool
	 * @throws CreateInputXmlException
	 */
	protected function validateValues(array $values, int $propertyKey): bool {
		if (empty($values)) {
			//Empty values is invalid
			throw new CreateInputXmlException("Property #$propertyKey: Values is empty");
		}
		foreach ($values as $key => $value) {
			if (!(count($value) === 2 && isset($value['time']) && isset($value['value']))) {
				//Invalid sub-array
				throw new CreateInputXmlException("Property #$propertyKey, Value #$key: 'time' and 'value' keys are required");
			}
			if (DateTime::createFromFormat(DateTime::ISO8601, $value['time']) === false) {
				//Invalid data format
				throw new CreateInputXmlException("Property #$propertyKey, Value #$key: Time format is invalid");
			}
			if (!is_numeric($value['value'])) {
				//Invalid value format
				throw new CreateInputXmlException("Property #$propertyKey, Value #$key: Value format is invalid");
			}
		}

		//Values are valid
		return true;
	}


}
