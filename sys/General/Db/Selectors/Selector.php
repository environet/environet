<?php


namespace Environet\Sys\General\Db\Selectors;

use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Identity;
use TypeError;

/**
 * Class Selector
 *
 * Base class for selector fields
 *
 * @package Environet\Sys\General\Db\Selectors
 * @author  SRG Group <dev@srg.hu>
 */
abstract class Selector {


	public const string SELECTOR_TYPE_INT   = 'int';
	public const string SELECTOR_TYPE_FLOAT = 'float';
	public const string SELECTOR_TYPE_STRING = 'string';

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
	 *
	 * @uses Selector::unserialize
	 */
	public function __construct(string $values, string $valueType = self::SELECTOR_TYPE_INT) {
		$this->valueType = $valueType;
		$this->unserialize($values);
	}


	/**
	 * Find the user, and create a new Identity based on the operator id
	 *
	 *
	 * @throws QueryException
	 * @uses Select::run
	 * @see  Identity|null
	 */
	protected function getOperatorIdentity($operatorId): ?Identity {
		$user = new Select()
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
	 */
	public function unserialize($serialized) {
		if (is_string($serialized)) {
			$this->values = match ($this->valueType) {
				self::SELECTOR_TYPE_INT => array_filter(array_map('intval', explode(',', $serialized))),
				self::SELECTOR_TYPE_FLOAT => array_filter(array_map('floatval', explode(',', $serialized))),
				default => array_filter(explode(',', $serialized)),
			};

			return;
		}

		throw new TypeError('The serialized data must be a string!');
	}


	/**
	 * Get selector values.
	 *
	 */
	public function getValues(): array {
		return $this->values;
	}


	/**
	 * Add one value to the selector.
	 * The value is filtered for uniqueness, it's not possible to add a value more than one time.
	 *
	 *
	 */
	public function addValue($value): Selector {
		$this->values[] = match ($this->valueType) {
			self::SELECTOR_TYPE_INT => (int) $value,
			self::SELECTOR_TYPE_FLOAT => (float) $value,
			default => "$value",
		};

		$this->values = array_unique($this->values);

		return $this;
	}


	/**
	 * Remove a value from the selector.
	 * If the value isn't included, doesn't do anything.
	 *
	 *
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
	 *
	 */
	public function setValues(array $values): Selector {
		$this->values = $values;

		return $this;
	}
}