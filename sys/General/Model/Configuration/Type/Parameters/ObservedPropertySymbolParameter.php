<?php

namespace Environet\Sys\General\Model\Configuration\Type\Parameters;

use Environet\Sys\Plugins\Resource;

/**
 * Class ObservedPropertySymbolParameter
 *
 * @package Environet\Sys\General\ConfigurationModels
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class ObservedPropertySymbolParameter extends AbstractFormatParameter {

	protected ?string $variable = null;


	/**
	 * @param array $config
	 *
	 * @return AbstractFormatParameter
	 */
	public function setOptions(array $config): AbstractFormatParameter {
		parent::setOptions($config);

		// Set variable from Variable, or the old Value field
		if (!empty(trim($config['Variable']))) {
			$this->variable = trim($config['Variable']);
		} elseif (!empty(trim($config['Value']))) {
			$this->variable = trim($config['Value']);
		}

		return $this;
	}


	/**
	 * @return string|null
	 */
	public function getVariable(): ?string {
		return $this->variable;
	}


	/**
	 * Get internal symbol for observed property from external symbol. Conversion between symbols is given by variable conversion information.
	 *
	 * @param Resource $resource Parse resource
	 * @param string   $symbol   external symbol for observed property
	 *
	 * @return string internal symbol for observed property
	 */
	public function mapObservedPropertySymbol(Resource $resource, string $symbol): string {
		if (!$this->getVariable()) {
			//If no variable is set, return empty string, can't map
			return '';
		}
		foreach ($resource->getObservedPropertyConversions() as $key => $value) {
			//Get symbol from conversion array
			if (is_array($value) && $value[$this->getVariable()] && $value[$this->getVariable()] == $symbol) {
				return $key;
			}
		}

		return '';
	}


}
