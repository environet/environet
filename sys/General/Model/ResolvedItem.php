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


	/**
	 * @param mixed                   $value
	 */
	public function __construct(protected AbstractFormatParameter $parameter, protected $value) {
	}


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
	 */
	public function setValue($value): ResolvedItem {
		$this->value = $value;

		return $this;
	}


}
