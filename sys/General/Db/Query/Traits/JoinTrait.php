<?php

namespace Environet\Sys\General\Db\Query\Traits;

use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\Query\Update;

/**
 * Trait JoinTrait
 *
 * Trait for queries which are able to use join clauses.
 *
 * @package Environet\Sys\General\Db\Query\Traits
 * @author  SRG Group <dev@srg.hu>
 */
trait JoinTrait {

	/**
	 * Array of join statements, in an array format
	 *
	 * [
	 *    'table' => 'joinTable',
	 *    'on' => 'ON condition',
	 *    'joinType' => 'LEFT, RIGHT, INNER, OUTER'
	 * ]
	 *
	 * @var array
	 */
	protected $joins = [];


	/**
	 * Add a join part with table name, ON condition and optional join type
	 *
	 * @param string $table    The joined table name
	 * @param string $on       The ON condition of join part
	 * @param string $joinType Optionally the join's type (OUTER, INNER...)
	 *
	 * @return Query|Select|Update
	 */
	public function join(string $table, string $on, $joinType = Query::JOIN_INNER): self {
		$this->joins[] = [
			'table'    => $table,
			'on'       => $on,
			'joinType' => $joinType
		];

		return $this;
	}


	/**
	 * Clear join parts
	 * @return self
	 */
	public function clearJoins(): self {
		$this->joins = [];

		return $this;
	}


	/**
	 * Build the join parts, and 'render' all parts to the given $queryString parameter as a string
	 *
	 * @param array $queryString Reference to $queryString array
	 */
	protected function buildJoinClause(array &$queryString) {
		foreach ($this->joins as $join) {
			$queryString[] = "{$join['joinType']} JOIN {$join['table']} ON {$join['on']}";
		}
	}


}
