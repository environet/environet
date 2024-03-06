<?php

namespace Environet\Sys\General\Model\Configuration\Type\Parameters;

use SimpleXMLElement;

/**
 * Class ObservedPropertyValueParameter
 *
 * @package Environet\Sys\General\ConfigurationModels
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class ObservedPropertyValueParameter extends AbstractFormatParameter {

	protected ?string $symbol = null;

	protected ?string $valueConversion = null;


	/**
	 * @param array $config
	 *
	 * @return AbstractFormatParameter
	 */
	public function setOptions(array $config): AbstractFormatParameter {
		parent::setOptions($config);

		// Set symbol from Symbol, or the old Value field
		if (!empty(trim($config['Symbol']))) {
			$this->symbol = trim($config['Symbol']);
		} elseif (!empty(trim($config['Value']))) {
			$this->symbol = trim($config['Value']);
		}

		if (!empty(trim($config['ValueConversion']))) {
			$this->valueConversion = trim($config['ValueConversion']);
		}

		// Set optional from Optional, or the old optional field
		if (!empty(trim($config['Optional']))) {
			$this->optional = $config['Optional'] === true;
		} elseif (!empty(trim($config['optional']))) {
			$this->optional = $config['optional'] === true;
		}

		return $this;
	}


	/**
	 * @return string|null
	 */
	public function getSymbol(): ?string {
		return $this->symbol;
	}


	/**
	 * @return string|null
	 */
	public function getValueConversion(): ?string {
		return $this->valueConversion;
	}


	/**
	 * Handle skipEmptyValueTag option for value paramters
	 *
	 * @param SimpleXMLElement $element
	 * @param bool             $skipEmptyValueTag
	 *
	 * @return string
	 */
	public function getXmlValue(SimpleXMLElement $element, bool $skipEmptyValueTag = false): string {
		$value = parent::getXmlValue($element);
		if (!$skipEmptyValueTag && $value === '') {
			//Convert empty values to 0
			return '0';
		}

		return $value;
	}


	/**
	 * Handle value conversion for value parameters
	 *
	 * @param float $value
	 *
	 * @return float
	 */
	public function convertValueUnit(float $value): float {
		if ($this->valueConversion && preg_match('/^([\/*])(\d+)$/', $this->valueConversion, $match)) {
			if ($match[1] === '/') {
				$value = $value / $match[2];
			} elseif ($match[1] === '*') {
				$value = $value * $match[2];
			}
		}

		return $value;
	}


}
