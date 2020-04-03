<?php


namespace Environet\Sys\General\Db\Query;

use Environet\Sys\General\Db\Query\Traits\JoinTrait;
use Environet\Sys\General\Db\Query\Traits\WhereTrait;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class Update
 *
 * Query class for update operations
 *
 * @package Environet\Sys\General\Db\Query
 * @author  SRG Group <dev@srg.hu>
 */
class Update extends Query {

	use WhereTrait, JoinTrait;

	/**
	 * Set operations. Each item must be a string, e.g.: "column = value"
	 * @var array
	 */
	protected $sets;


	/**
	 * Add a single setter with column and value
	 *
	 * @param string $column The column name to set
	 * @param string $value  The value to set. It must be a pdo param definition
	 *
	 * @return self
	 * @throws QueryException
	 * @uses \checkDbInputValues()
	 */
	public function addSet(string $column, $value): self {
		checkDbInputValues($value);

		$this->sets[$column] = $value;

		return $this;
	}


	/**
	 * Set the setters.
	 * It resets the sets property before adding new items.
	 *
	 * @param array $sets Each item is a string in format "column = value"
	 *
	 * @return self
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Update::addSet()
	 */
	public function setSets(array $sets): self {
		$this->sets = [];
		foreach ($sets as $key => $value) {
			// Add setter
			$this->addSet($key, $value);
		}

		return $this;
	}


	/**
	 * Update with data array.
	 * This sets the columns from the keys, and the values from the array values.
	 *
	 * @param array $data
	 *
	 * @return $this
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Update::setParameters()
	 * @uses \Environet\Sys\General\Db\Query\Update::setSets()
	 */
	public function updateData(array $data): self {
		// Process data, convert it to PDO-compatible parameters and parameter names
		$parameters = [];
		foreach ($data as $key => $value) {
			$parameters[':' . $key] = $value;
			$data[$key] = ':' . $key;
		}

		// Set parameters
		$this->setParameters($parameters);
		$this->setSets($data);

		return $this;
	}


	/**
	 * Validate the query.
	 * It's invalid if the sets count is 0.
	 *
	 * @return bool
	 * @uses \Environet\Sys\General\Db\Query\Query::validateQuery()
	 */
	protected function validateQuery(): bool {
		$setCount = count($this->sets);
		if ($setCount === 0) {
			return false;
		}

		return parent::validateQuery();
	}


	/**
	 * Build a update operation query with conditions and setters
	 *
	 * @return mixed|string
	 * @uses \Environet\Sys\General\Db\Query\Update::buildWhereClause()
	 * @uses \Environet\Sys\General\Db\Query\Update::buildJoinClause()
	 * @uses \Environet\Sys\General\Db\Query\Update::buildHavingClause()
	 */
	public function buildQuery() {
		// Build UPDATE table
		$queryString = [
			'UPDATE',
			$this->table,
			'SET',
		];

		//Add setters
		$setString = [];
		foreach ($this->sets as $key => $value) {
			$setString[] = "$key = $value";
		}
		$queryString[] = implode(', ', $setString);

		// Add where clause
		$this->buildWhereClause($queryString);

		// Add join clause
		$this->buildJoinClause($queryString);

		// Add having clause
		$this->buildHavingClause($queryString);

		return implode(' ', $queryString);
	}


	/**
	 * For update run must return a simple boolean if success
	 *
	 * @param null $flags Original flags
	 *
	 * @return int|null
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Query::run()
	 */
	public function run($flags = null) {
		$flags |= self::RETURN_BOOL;

		return parent::run($flags);
	}


}
