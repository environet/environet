<?php


namespace Environet\Sys\General\Db\Selectors;

/**
 * Class Selector
 *
 * Base class for selector fields
 *
 * @package Environet\Sys\General\Db\Selectors
 * @author  SRG Group <dev@srg.hu>
 */
abstract class Selector {

	const SELECTOR_TYPE_INT    = 'int';
	const SELECTOR_TYPE_FLOAT  = 'float';
	const SELECTOR_TYPE_STRING = 'string';

	/**
	 * @var array Selector values (eg.: ids)
	 */
	protected $values = [];

	/**
	 * @var string
	 */
	private $valueType;


	/**
	 * Selector constructor.
	 *
	 * @param string $values
	 * @param string $valueType
	 *
	 * @uses \Environet\Sys\General\Db\Selectors\Selector::unserialize()
	 */
	public function __construct(string $values, string $valueType = self::SELECTOR_TYPE_INT) {
		$this->valueType = $valueType;
		$this->unserialize($values);
	}


	/**
	 * @return string
	 */
	public function serialize() {
		return implode(',', $this->values);
	}


	/**
	 * @param $serialized
	 */
	public function unserialize($serialized) {
		if (is_string($serialized)) {
			switch ($this->valueType) {
				case self::SELECTOR_TYPE_INT:
					$this->values = array_filter(array_map('intval', explode(',', $serialized)));
					break;
				case self::SELECTOR_TYPE_FLOAT:
					$this->values = array_filter(array_map('floatval', explode(',', $serialized)));
					break;
				case self::SELECTOR_TYPE_STRING:
				default:
					$this->values = array_filter(explode(',', $serialized));
					break;
			}

			return;
		}

		throw new \TypeError('The serialized data must be a string!');
	}


	/**
	 * @return array
	 */
	public function getValues(): array {
		return $this->values;
	}


	/**
	 * @param $value
	 *
	 * @return Selector
	 */
	public function addValue($value): Selector {
		switch ($this->valueType) {
			case self::SELECTOR_TYPE_INT:
				$this->values[] = (int) $value;
				break;
			case self::SELECTOR_TYPE_FLOAT:
				$this->values[] = (float) $value;
				break;
			case self::SELECTOR_TYPE_STRING:
			default:
				$this->values[] = "$value";
				break;
		}

		$this->values = array_unique($this->values);

		return $this;
	}


	/**
	 * @param $value
	 *
	 * @return Selector
	 */
	public function removeValue($value): Selector {
		if (($key = array_search($value, $this->values)) !== false) {
			unset($this->values[$key]);
		}
	}


	/**
	 * @param array $values
	 *
	 * @return Selector
	 */
	public function setValues(array $values): Selector {
		$this->values = $values;

		return $this;
	}
}