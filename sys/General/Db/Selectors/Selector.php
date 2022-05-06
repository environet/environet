<?php


namespace Environet\Sys\General\Db\Selectors;

use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Identity;

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
	 * Find the user, and create a new Identity based on the operator id
	 *
	 * @param $operatorId
	 *
	 * @return Identity
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 * @see  Identity|null
	 */
	protected function getOperatorIdentity($operatorId): ?Identity {
		$user = (new Select())
			->select('users.*')
			->from('users')
			->join('operator_users', 'operator_users.usersid = users.id')
			->where("operator_users.operatorid = $operatorId")
			->limit(1)
			->run(Query::FETCH_FIRST);

		return isset($user['id']) ? new Identity($user['id'], $user) : null;
	}


	/**
	 * Serialize the selector data into a comma separated string.
	 *
	 * @return string
	 */
	public function serialize() {
		return implode(',', $this->values);
	}


	/**
	 * Unserialize the selector data from a comma separated string.
	 *
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
	 * Get selector values.
	 *
	 * @return array
	 */
	public function getValues(): array {
		return $this->values;
	}


	/**
	 * Add one value to the selector.
	 * The value is filtered for uniqueness, it's not possible to add a value more than one time.
	 *
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
	 * Remove a value from the selector.
	 * If the value isn't included, doesn't do anything.
	 *
	 * @param $value
	 *
	 * @return Selector
	 */
	public function removeValue($value): Selector {
		if (($key = array_search($value, $this->values)) !== false) {
			unset($this->values[$key]);
		}

		return $this;
	}


	/**
	 * Overwrite all selector values with a new set.
	 *
	 * @param array $values
	 *
	 * @return Selector
	 */
	public function setValues(array $values): Selector {
		$this->values = $values;

		return $this;
	}
}