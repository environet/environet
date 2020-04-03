<?php

namespace Environet\Sys\General\Db\Query\Traits;

use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Delete;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\Query\Update;

/**
 * Trait WhereTrait
 *
 * Trait for queries which can use where and having clauses.
 *
 * @package Environet\Sys\General\Db\Query\Traits
 * @author  SRG Group <dev@srg.hu>
 */
trait WhereTrait {

	/**
	 * Array of the where conditions.
	 *
	 * There are three valid cases to specify the where condition:
	 * 1. If the value is a string, it will be appended to the wheres array with the parent array's key as logical operator.
	 * 2. If the key is a string ('AND' or 'OR) the sub-array will be processed, and each sub-condition will be added under the specified logical operator.
	 * 3. The array can be a non-associative array, which can contains multiple array with different logical operators.
	 *
	 * The root key must be 'AND' and/or 'OR'
	 *
	 * Example:
	 *
	 * [
	 *    'AND' => [
	 *        'a = 1',
	 *        'b = 2,
	 *        'OR' => [
	 *            'd >= 2'
	 *        ],
	 *        [
	 *          'OR' => [
	 *              'c = 3'
	 *          ],
	 *          'OR' => [
	 *              'c = 4'
	 *          ]
	 *        ]
	 *    ],
	 *    'OR' => [
	 *          'd = 4'
	 *      ]
	 * ]
	 *
	 * @var array
	 */
	protected $wheres = [];

	/**
	 * Array of the having conditions. The structure is the same as the structure of where property
	 *
	 * @var array
	 */
	protected $havings = [];


	/**
	 * Add a where condition. It can be a multidimensional array, the structure must be in a structure of the where property
	 * With the operator parameter you can add the condition to the 'AND' or 'OR' clause of the where condition's root.
	 *
	 * @param array|string $whereCondition String for single condition, multidimensional array for more complex logical structures
	 * @param string       $operator       The root operator of the added condition
	 *
	 * @return WhereTrait|Select|Update|Delete
	 */
	public function where($whereCondition, $operator = Query::OPERATOR_AND): self {
		if (!isset($this->wheres[$operator])) {
			$this->wheres[$operator] = [];
		}
		$this->wheres[$operator][] = $whereCondition;

		return $this;
	}


	/**
	 * Add a where-in condition. It can be a bit complex to defined IN condition with PDO parameters.
	 * This method create it with named parameters.
	 *
	 * @param string $field       The field where we find values
	 * @param array  $array       Array of values which should be included in the list
	 * @param string $paramPrefix Parameters will start with this prefix
	 * @param string $operator    The root operator of the added condition
	 *
	 * @return WhereTrait|Select|Update|Delete
	 * @uses \Environet\Sys\General\Db\Query\Traits\WhereTrait::where()
	 * @uses \Environet\Sys\General\Db\Query\Query::addParameter()
	 */
	public function whereIn(string $field, array $array, string $paramPrefix, $operator = Query::OPERATOR_AND): self {
		$inParams = [];
		foreach ($array as $key => $item) {
			//Create parameter name (e.g :id0)
			$param = ":{$paramPrefix}{$key}";

			//Add the parameter to the array which will be imploded for IN condition
			$inParams[] = $param;

			//Add the named parameter, and attach the value to it
			$this->addParameter($param, $item);
		}

		//Generate the condition as a simple where condition
		return $this->where("$field IN (" . implode(',', $inParams) . ')');
	}


	/**
	 * Add a having condition. It can be a multidimensional array, the structure must be in a structure of the having property
	 * With the operator parameter you can add the condition to the 'AND' or 'OR' clause of the having condition's root.
	 *
	 * @param array|string $havingCondition String for single condition, multidimensional array for more complex logical structures
	 * @param string       $operator        The root operator of the added condition
	 *
	 * @return WhereTrait|Select|Update|Delete
	 */
	public function having($havingCondition, $operator = Query::OPERATOR_AND): self {
		if (!isset($this->havings[$operator])) {
			$this->havings[$operator] = [];
		}
		$this->havings[$operator][] = $havingCondition;

		return $this;
	}


	/**
	 * Build condition string based on multidimensional array.
	 * It's a recursive function, which calls itself walking on the multidimensional array
	 *
	 * @param array  $conditions The array-structure of conditions
	 * @param string $operator
	 *
	 * @return string
	 */
	protected function buildConditions(array $conditions, string $operator = Query::OPERATOR_AND) {
		$parts = [];
		foreach ($conditions as $subOperator => $condition) {
			if ($subOperator === Query::OPERATOR_AND || $subOperator === Query::OPERATOR_OR) {
				$parts[] = $this->buildConditions($condition, $subOperator);
			} elseif (is_array($condition)) {
				foreach ($condition as $subSubOperator => $subCondition) {
					$parts[] = $this->buildConditions($subCondition, $subSubOperator);
				}
			} else {
				$parts[] = $condition;
			}
		}

		return '(' . implode(" $operator ", $parts) . ')';
	}


	/**
	 * Build the condition string for WHERE clause, and append it to the $queryString reference
	 *
	 * @param array $queryString
	 *
	 * @return void
	 * @uses \Environet\Sys\General\Db\Query\Traits\WhereTrait::buildConditions()
	 */
	protected function buildWhereClause(array &$queryString) {
		if (count($this->wheres) > 0) {
			$queryString[] = 'WHERE ' . $this->buildConditions($this->wheres);
		}
	}


	/**
	 * Build the condition string for HAVING clause, and append it to the $queryString reference
	 *
	 * @param array $queryString
	 *
	 * @return void
	 * @uses \Environet\Sys\General\Db\Query\Traits\WhereTrait::buildConditions()
	 */
	protected function buildHavingClause(array &$queryString) {
		if (count($this->havings) > 0) {
			$queryString[] = 'HAVING ' . $this->buildConditions($this->havings);
		}
	}


	/**
	 * Prepare search part on a query.
	 *
	 * @param array $needle
	 * @param array $searchableFields
	 *
	 * @return WhereTrait|Select|Update|Delete
	 * @uses \makeAccentInsensitiveRegex()
	 * @uses \Environet\Sys\General\Db\Query\Traits\WhereTrait::where()
	 */
	public function search(array $needle, array $searchableFields) {
		$index = 0;

		// Expected structure of query: ((word1 = field1 OR word1 = field2) AND (word2 = field1 OR word2 = field2))
		// Each word must be found in one of the fields
		foreach ($needle as $searchWord) {
			// OR container for fields
			$or = [];
			foreach ($searchableFields as $field) {
				$param = ':' . $index;
				$this->addParameter($param, makeAccentInsensitiveRegex($searchWord));
				// Add the field=word condition as an OR-part
				$or[] = $field . ' ~* ' . $param;
			}

			// Add the ORs of the word as an AND condition
			$this->where([Query::OPERATOR_OR => $or]);
			$index ++;
		}

		return $this;
	}


}
