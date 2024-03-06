<?php

namespace Environet\Sys\General\Model\Configuration\Type\Parameters;

use Exception;
use SimpleXMLElement;

/**
 * Class AbstractFormatParameter
 *
 * Parameter model for format configuration paramter type.
 *
 * @package Environet\Sys\General\ConfigurationModels
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
abstract class AbstractFormatParameter {

	protected ?string $attribute = null;

	protected array $tagHierarchy = [];

	protected bool $optional = false;


	/**
	 * @param array $config
	 *
	 * @return static
	 * @throws Exception
	 */
	public static function fromConfig(array $config): AbstractFormatParameter {
		$type = $config['Parameter'] ?? null;
		switch ($type) {
			case 'MonitoringPoint':
				$parameter = new MonitoringPointParameter;
				break;
			case 'ObservedPropertyValue':
				$parameter = new ObservedPropertyValueParameter;
				break;
			case 'ObservedPropertySymbol':
				$parameter = new ObservedPropertySymbolParameter;
				break;
			case 'DateTime':
			case 'Date':
			case 'Time':
			case 'Year':
			case 'Month':
			case 'Day':
			case 'Hour':
			case 'Minute':
			case 'Second':
				//Create date parameter for all date related types, date type will be stored in the parameter
				$parameter = new DateParameter;
				break;
			default:
				throw new Exception('Invalid parameter type: ' . $type);
		}
		$parameter->setOptions($config);

		return $parameter;
	}


	/**
	 * @param array $config
	 *
	 * @return $this
	 */
	public function setOptions(array $config): AbstractFormatParameter {
		if (isset($config['Attribute']) && !empty(trim($config['Attribute']))) {
			$this->attribute = trim($config['Attribute']);
		}

		//Set TagHierarchy
		if (isset($config['Tag Hierarchy'])) { //Convert it to the new format, without spaces
			$config['TagHierarchy'] = $config['Tag Hierarchy'];
		}
		if (isset($config['TagHierarchy']) && is_array($config['TagHierarchy'])) {
			$this->tagHierarchy = array_values(array_map('trim', $config['TagHierarchy']));
		}

		return $this;
	}


	/**
	 * @return string|null
	 */
	public function getAttribute(): ?string {
		return $this->attribute;
	}


	/**
	 * @return array
	 */
	public function getTagHierarchy(): array {
		return $this->tagHierarchy;
	}


	/**
	 * @return bool
	 */
	public function isOptional(): bool {
		return $this->optional;
	}


	/**
	 * Get the XPath of the parameter, optionally under a given path.
	 *
	 * @param string $underPath
	 *
	 * @return string
	 */
	public function getXPath(string $underPath = ''): string {
		$underPath = explode('/', trim($underPath, '/'));
		$tagHierarchy = array_diff($this->tagHierarchy, $underPath);

		return implode('/', $tagHierarchy);
	}


	/**
	 * Get the value of the parameter from the given XML element. If the parameter is an attribute, the attribute value is returned, otherwise the element's value.
	 *
	 * @param SimpleXMLElement $element
	 * @param bool             $skipEmptyValueTag
	 *
	 * @return string
	 */
	public function getXmlValue(SimpleXMLElement $element, bool $skipEmptyValueTag = false): string {
		if ($this->getAttribute()) {
			return $element->attributes()[$this->getAttribute()]->__toString();
		} else {
			return $element->__toString();
		}
	}


}
