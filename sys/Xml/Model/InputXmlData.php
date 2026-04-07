<?php

namespace Environet\Sys\Xml\Model;

/**
 * Class InputXmlData
 *
 * Data model for input XML element
 *
 * @package Environet\Sys\Xml\Model
 * @author  SRG Group <dev@srg.hu>
 */
class InputXmlData {

	/**
	 * Id of monitoring point
	 * @var string
	 */
	protected $pointId;

	/**
	 * Array of properties data
	 * @var array|InputXmlPropertyData[]
	 */
	protected $properties = [];


	/**
	 * InputXmlData constructor.
	 *
	 *
	 * @uses InputXmlData::setPointId
	 * @uses InputXmlData::addProperty
	 */
	public function __construct(?string $pointId = null, ?array $properties = null) {
		if (!is_null($pointId)) {
			$this->setPointId($pointId);
		}
		if (!is_null($properties)) {
			foreach ($properties as $property) {
				$this->addProperty($property);
			}
		}
	}


	/**
	 * Get monitoring point id
	 *
	 */
	public function getPointId(): ?string {
		return $this->pointId;
	}


	/**
	 * Set monitoring point id
	 *
	 *
	 */
	public function setPointId(string $pointId): InputXmlData {
		$this->pointId = $pointId;

		return $this;
	}


	/**
	 * Get array of property data
	 *
	 * @return array|InputXmlPropertyData[]
	 */
	public function getProperties(): array {
		return $this->properties;
	}


	/**
	 * Add a property data to properties array
	 *
	 *
	 * @return $this
	 */
	public function addProperty(InputXmlPropertyData $inputXmlPropertyData) {
		$this->properties[] = $inputXmlPropertyData;

		return $this;
	}


}
