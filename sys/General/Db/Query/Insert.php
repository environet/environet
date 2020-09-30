<?php


namespace Environet\Sys\General\Db\Query;

use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class Insert
 *
 * Query class for insert operations
 *
 * @package Environet\Sys\General\Db\Query
 * @author  SRG Group <dev@srg.hu>
 */
class Insert extends Query {

	/**
	 * Array of columns to insert. Values should match to the count and the order of this array
	 *
	 * @var array
	 */
	protected $columns = [];

	/**
	 * Inserted values. It should be a multidimensional array, each item is the array of values.
	 * This sub-array count should be equal to the count of columns property
	 *
	 * @var array
	 */
	protected $values = [];

	/**
	 * @var array|null
	 */
	protected $ignoreConflictFields = null;


	/**
	 * Set columns array. It overwrites all previous value.
	 *
	 * @param array $columns
	 *
	 * @return $this
	 */
	public function columns(array $columns): self {
		$this->columns = array_values($columns);

		return $this;
	}


	/**
	 * Set the values with a multidimensional array.
	 *
	 * It resets the {@see Insert::$values} property.
	 *
	 * @param array $valueRows
	 *
	 * @return Insert
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Insert::addValueRow()
	 */
	public function setValueRows(array $valueRows): self {
		$this->values = [];
		foreach ($valueRows as $valueRow) {
			$this->addValueRow($valueRow);
		}

		return $this;
	}


	/**
	 * Add a single set of data to the values array.
	 *
	 * It represents a row in the database. The count and the order must be the same as in the columns property.
	 * Values in the array must be PDO-parameters.
	 *
	 * @param array $valueRow
	 *
	 * @return Insert
	 * @throws QueryException
	 * @uses \checkDbInputValues()
	 */
	public function addValueRow(array $valueRow): self {
		checkDbInputValues($valueRow);
		$this->values[] = array_values($valueRow);

		return $this;
	}


	/**
	 * Add a single row with a single function call. This sets the columns from the keys, and the values from the array values
	 *
	 * Values in the array must be PDO-parameters
	 *
	 * @param array $data
	 *
	 * @return $this
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Insert::setParameters()
	 * @uses \Environet\Sys\General\Db\Query\Insert::columns()
	 * @uses \Environet\Sys\General\Db\Query\Insert::addValueRow()
	 */
	public function addSingleData(array $data): self {
		// Process data, convert it to PDO-compatible parameters and parameter names
		$parameters = [];
		foreach ($data as $key => $value) {
			$parameters[':' . $key] = $value;
		}

		// Set parameters
		$this->setParameters($parameters);

		// Set columns and values
		$this->columns(array_keys($data))
		     ->addValueRow(array_keys($parameters));

		return $this;
	}


	/**
	 * Add on conflict statement
	 *
	 * @param array $fields
	 *
	 * @return Insert
	 */
	public function ignoreConflict(array $fields): self {
		$this->ignoreConflictFields = $fields;
		return $this;
	}


	/**
	 * Validates the query properties.
	 * It's invalid if no columns and values has been set, or if there are any count-mismatch
	 *
	 * @return bool
	 * @see Query::validateQuery()
	 */
	protected function validateQuery(): bool {
		$columnsCount = count($this->columns);
		$valuesCount = count($this->values);
		if ($columnsCount === 0 || $valuesCount === 0) {
			return false;
		}
		foreach ($this->values as $valueRow) {
			if (count($valueRow) !== $columnsCount) {
				return false;
			}
		}

		return parent::validateQuery();
	}


	/**
	 * Build an insert operation query with columns and multiple values
	 *
	 * @return mixed|string
	 */
	public function buildQuery() {
		// Build INSERT INTO table
		$queryString = [
			'INSERT INTO',
			$this->table,
			'(' . implode(', ', $this->columns) . ')', // Add column definition
			'VALUES'
		];

		// Add multiple value rows
		$values = [];
		foreach ($this->values as $valueRow) {
			$values[] = '(' . implode(', ', $valueRow) . ')';
		}
		$queryString[] = implode(',', $values);

		if ($this->ignoreConflictFields) {
			$conflictFields = implode(',', $this->ignoreConflictFields);
			$queryString[] = "ON CONFLICT ($conflictFields) DO NOTHING";
		}

		return implode(' ', $queryString) . ';';
	}


	/**
	 * For insert run must return the last inserted id, or null in case of error.
	 *
	 * @param null $flags Original flags
	 *
	 * @return int|null
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Query::run()
	 */
	public function run($flags = null) {
		// Extend
		$flags |= self::RETURN_ID;
		$id = parent::run($flags);

		return $id ? (int) $id : null;
	}


}
