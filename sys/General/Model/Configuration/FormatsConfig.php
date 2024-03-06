<?php

namespace Environet\Sys\General\Model\Configuration;

use Environet\Sys\General\Model\Configuration\Type\Parameters\AbstractFormatParameter;
use Environet\Sys\General\Model\Configuration\Type\Parameters\MonitoringPointParameter;
use Environet\Sys\General\Model\Configuration\Type\Parameters\ObservedPropertySymbolParameter;
use Exception;

/**
 * Class FormatsConfig
 *
 * Model for formats configuration (formats.json)
 *
 * @package Environet\Sys\General\ConfigurationModels
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class FormatsConfig {

	/**
	 * @var array<AbstractFormatParameter>
	 */
	protected array $parameters = [];


	/**
	 * @param array $formatsConfig
	 *
	 * @throws Exception
	 */
	public function __construct(array $formatsConfig) {
		foreach ($formatsConfig as $parameter) {
			//Create parameter model for each parameter
			$this->parameters[] = AbstractFormatParameter::fromConfig($parameter);
		}
	}


	/**
	 * @return bool
	 */
	public function count(): bool {
		return count($this->parameters);
	}


	/**
	 * @return array|AbstractFormatParameter[]
	 */
	public function getParameters(): array {
		return $this->parameters;
	}


	/**
	 * Get the only one monitoring point parameter (multiple monitoring point parameters are not allowed in the configuration)
	 * @return MonitoringPointParameter|null
	 */
	public function getMonitoringPointParameter(): ?MonitoringPointParameter {
		foreach ($this->parameters as $parameter) {
			if ($parameter instanceof MonitoringPointParameter) {
				return $parameter;
			}
		}

		return null;
	}


	/**
	 * Get the only one observed property symbol parameter (multiple observed property symbol parameters are not allowed in the configuration)
	 * @return ObservedPropertySymbolParameter|null
	 */
	public function getPropertySymbolParameter(): ?ObservedPropertySymbolParameter {
		foreach ($this->parameters as $parameter) {
			if ($parameter instanceof ObservedPropertySymbolParameter) {
				return $parameter;
			}
		}

		return null;
	}


}
