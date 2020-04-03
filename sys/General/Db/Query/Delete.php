<?php


namespace Environet\Sys\General\Db\Query;

use Environet\Sys\General\Db\Query\Traits\WhereTrait;

/**
 * Class Delete
 *
 * Query class for delete operations
 *
 * @package Environet\Sys\General\Db\Query
 * @author  SRG Group <dev@srg.hu>
 */
class Delete extends Query {

	use WhereTrait;


	/**
	 * Build a delete operation query with where conditions
	 *
	 * @return mixed|string
	 * @uses \Environet\Sys\General\Db\Query\Traits\WhereTrait::buildWhereClause()
	 */
	public function buildQuery() {
		// Build DELETE FROM table
		$queryString = ['DELETE FROM', $this->table];

		// Add where conditions
		$this->buildWhereClause($queryString);

		return implode(' ', $queryString) . ';';
	}


}
