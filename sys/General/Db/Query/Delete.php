<?php


namespace Environet\Sys\General\Db\Query;

use Environet\Sys\General\Db\Query\Traits\WhereTrait;

/**
 * Class Delete
 *
 * Query class for delete operations
 *
 * @package Environet\Sys\General\Db\Query
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class Delete extends Query {

	use WhereTrait;


	/**
	 * Build a delete operation query with where conditions
	 */
	public function buildQuery() {
		//Build DELETE FROM table
		$queryString = ['DELETE'];
		$queryString[] = 'FROM';
		$queryString[] = $this->table;

		//Add where conditions
		$this->buildWherePart($queryString);

		return implode(' ', $queryString).';';
	}


}
