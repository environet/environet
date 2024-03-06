<?php

namespace Environet\Sys\General\Model\Configuration\Type\Parameters;

/**
 * Class DateParameter
 *
 * @package Environet\Sys\General\ConfigurationModels
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class DateParameter extends AbstractFormatParameter {

	protected ?string $format = null;

	protected ?string $dateType = null;


	/**
	 * @param array $config
	 *
	 * @return AbstractFormatParameter
	 */
	public function setOptions(array $config): AbstractFormatParameter {
		parent::setOptions($config);

		// Set format from Format, or the old Value field
		if (!empty(trim($config['Format']))) {
			$this->format = trim($config['Format']);
		} elseif (!empty(trim($config['Value']))) {
			$this->format = trim($config['Value']);
		}

		// Set date type from Parameter
		if (!empty(trim($config['Parameter']))) {
			$this->dateType = trim($config['Parameter']);
		}

		return $this;
	}


	/**
	 * @return string|null
	 */
	public function getFormat(): ?string {
		return $this->format;
	}


	/**
	 * @return string|null
	 */
	public function getDateType(): ?string {
		return $this->dateType;
	}


}
