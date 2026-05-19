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


	public function setOptions(array $config): AbstractFormatParameter {
		parent::setOptions($config);

		// Set format from Format, or the old Value field
		if (!empty(trim((string) $config['Format']))) {
			$this->format = trim((string) $config['Format']);
		} elseif (!empty(trim((string) $config['Value']))) {
			$this->format = trim((string) $config['Value']);
		}

		// Set date type from Parameter
		if (!empty(trim((string) ($config['Parameter'] ?? null)))) {
			$this->dateType = trim((string) $config['Parameter']);
		}

		return $this;
	}


	public function getFormat(): ?string {
		return $this->format;
	}


	public function getDateType(): ?string {
		return $this->dateType;
	}


}
