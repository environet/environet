<?php

namespace Environet\Sys\Xml\Model;

/**
 * Class InputXmlPropertyData
 *
 * Data model for input xml's property element
 *
 * @package Environet\Sys\Xml\Model
 * @author  SRG Group <dev@srg.hu>
 */
class InputXmlPropertyData {

	/**
	 * Symbol of property
	 * @var string
	 */
	protected $propertySymbol;

	/**
	 * Values for the property - this will be time series
	 * @var array
	 */
	protected $values = [];


	/**
	 * InputXmlPropertyData constructor.
	 *
	 *
	 * @uses InputXmlPropertyData::setPropertySymbol
	 * @uses InputXmlPropertyData::setValues
	 */
	public function __construct(?string $propertySymbol = null, ?array $values = null) {
		if (!is_null($propertySymbol)) {
			$this->setPropertySymbol($propertySymbol);
		}
		if (!is_null($values)) {
			$this->setValues($values);
		}
	}


	/**
	 * Get property's symbol
	 *
	 */
	public function getPropertySymbol(): ?string {
		return $this->propertySymbol;
	}


	/**
	 * Set property's symbol
	 *
	 *
	 */
	public function setPropertySymbol(string $propertySymbol): InputXmlPropertyData {
		$this->propertySymbol = $propertySymbol;

		return $this;
	}


	/**
	 * Get values
	 *
	 */
	public function getValues(): array {
		return $this->values;
	}


	/**
	 * Set values
	 *
	 *
	 */
	public function setValues(array $values): InputXmlPropertyData {
		$this->values = $values;

		return $this;
	}


}

