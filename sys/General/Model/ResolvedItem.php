<?php

namespace Environet\Sys\General\Model;

use Environet\Sys\General\Model\Configuration\Type\Parameters\AbstractFormatParameter;

/**
 * Class ResolvedItem
 *
 * Model class for resolved item in XmlParser. It contains a resolved parameter and value.
 *
 * @package Environet\Sys\General\Model
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class ResolvedItem {

	protected AbstractFormatParameter $parameter;

	protected $value;


	/**
	 * @param AbstractFormatParameter $parameter
	 * @param mixed                   $value
	 */
	public function __construct(AbstractFormatParameter $parameter, $value) {
		$this->parameter = $parameter;
		$this->value = $value;
	}


	/**
	 * @return AbstractFormatParameter
	 */
	public function getParameter(): AbstractFormatParameter {
		return $this->parameter;
	}


	/**
	 * @return mixed
	 */
	public function getValue() {
		return $this->value;
	}


	/**
	 * @param mixed $value
	 *
	 * @return ResolvedItem
	 */
	public function setValue($value): ResolvedItem {
		$this->value = $value;

		return $this;
	}


}
