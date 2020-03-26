<?php


namespace Environet\Sys\General\Db\Query;

use Environet\Sys\General\Db\Query\Traits\JoinTrait;
use Environet\Sys\General\Db\Query\Traits\WhereTrait;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class Select
 *
 * Query class for select operations
 *
 * @package Environet\Sys\General\Db\Query
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class Select extends Query {

	use WhereTrait, JoinTrait;

	/**
	 * Array of select column definitions. Each item should be a strint
	 *
	 * @var array
	 */
	protected $selects = [];

	/**
	 * Array of order-by definitions. Each item should be in this format: "column DESC"
	 * @var array
	 */
	protected $orders = [];

	/**
	 * Array of unioned select statements
	 * @var array
	 */
	protected $unions = [];

	/**
	 * Limit part of the query
	 * @var int
	 */
	protected $limit = null;

	/**
	 * Offset part of the query
	 * @var int
	 */
	protected $offset = null;

	/**
	 * @var array
	 */
	protected $groupBy = [];


	/**
	 * Add some (one, or multiple) select columns
	 *
	 * @param array|string $selects If array, all items will be added the selects array.
	 *
	 * @return self
	 */
	public function select($selects): self {
		if (!is_array($selects)) {
			$selects = [$selects];
		}
		$this->selects = array_merge($this->selects, $selects);

		return $this;
	}


	/**
	 * Clear selects property
	 *
	 * @return self
	 */
	public function clearSelects(): self {
		$this->selects = [];

		return $this;
	}


	/**
	 * An alias for table option
	 *
	 * @param string $from Table name
	 *
	 * @return self
	 */
	public function from(string $from): self {
		return $this->table($from);
	}


	/**
	 * Add another select to this one, with an union connection
	 *
	 * @param Select $other
	 *
	 * @return Select
	 */
	public function union(Select $other) {
		$this->unions[] = $other->buildQuery();

		return $this;
	}


	/**
	 * Add an order-by condition
	 *
	 * @param string $column    The column part of the order-by condition
	 * @param string $direction The direction (ASC or DESC)
	 *
	 * @return self
	 */
	public function orderBy(string $column, $direction = self::DIR_ASC): self {
		$this->orders[] = $column . ' ' . $direction;

		return $this;
	}


	/**
	 * Clear order-by part of the query
	 *
	 * @return self
	 */
	public function clearOrderBy(): self {
		$this->orders = [];

		return $this;
	}


	/**
	 * Add an group-by condition
	 *
	 * @param string $column The column of group by
	 *
	 * @return self
	 */
	public function groupBy(string $column): self {
		$this->groupBy[] = $column;

		return $this;
	}


	/**
	 * Clear group-by part of the query
	 *
	 * @return self
	 */
	public function clearGroupBy(): self {
		$this->groupBy = [];

		return $this;
	}


	/**
	 * Add a limit query part
	 *
	 * @param int $limit Limit number
	 *
	 * @return self
	 */
	public function limit(int $limit): self {
		$this->limit = $limit;

		return $this;
	}


	/**
	 * Add a offset query part
	 *
	 * @param int $offset Offset number
	 *
	 * @return self
	 */
	public function offset(int $offset): self {
		$this->offset = $offset;

		return $this;
	}


	/**
	 * Build a select operation query with conditions
	 */
	public function buildQuery() {
		//Build SELECT columns FROM table
		$queryString = ['SELECT'];
		$queryString[] = $this->selects ? implode(', ', $this->selects) : '*';
		$queryString[] = 'FROM';
		$queryString[] = $this->table;

		//Add joins
		$this->buildJoinPart($queryString);

		//Add where conditions
		$this->buildWherePart($queryString);

		//Add having conditions
		$this->buildHavingPart($queryString);

		//Add group by parts if has any
		if (count($this->groupBy) > 0) {
			$queryString[] = 'GROUP BY ' . implode(', ', $this->groupBy);
		}

		//Add order by if has any
		if (count($this->orders) > 0) {
			$queryString[] = 'ORDER BY ' . implode(', ', $this->orders);
		}

		//Add order by if has any
		if (!is_null($this->limit)) {
			$queryString[] = 'LIMIT ' . $this->limit;
		}

		//Add order by if has any
		if (!is_null($this->offset)) {
			$queryString[] = 'OFFSET ' . $this->offset;
		}

		if (count($this->unions) > 0) {
			$queryString[] = 'UNION ' . implode('UNION ', $this->unions);
		}

		return implode(' ', $queryString);
	}


	/**
	 * Add limit and offset parameters to query to implement pagination.
	 * It also runs a cloned, cleaned query to get the total count (without pagination),
	 * and calculate page count
	 *
	 * @param int      $pageSize
	 * @param int|null $totalCount  Reference for storing total count (without pagination)
	 * @param int|null $currentPage Reference for storing the number of current page
	 * @param int|null $maxPage     Reference for storing page count
	 */
	public function paginate($pageSize, &$currentPage = null, &$totalCount = null, &$maxPage = null) {
		//Default limit and offset
		$limit = $pageSize;
		$offset = 0;

		if (!is_null($currentPage)) {
			//Page is set, calculate offset, and current page
			$currentPage = max(1, (int) $currentPage);
			$offset = ($currentPage - 1) * $limit;
		}

		try {
			//Clone the select query, clean it (selects, joins, etc)
			//And get the total count of rows
			$totalCount = (clone $this)
				->clearSelects()->clearGroupBy()->clearOrderBy()->clearJoins()
				->select('COUNT(*)')
				->run(Query::FETCH_COUNT);
		} catch (QueryException $e) {
			$totalCount = 0;
		}

		//Calculate max page based on total count
		$maxPage = (int) ceil($totalCount / $limit);

		//Set limit and offset
		$this->limit($limit)->offset($offset);
	}


	/**
	 * Modify query, add order_by part if request has order_by (and order_dir) parameters
	 *
	 * @param string|null $orderBy
	 * @param string|null $orderDir
	 */
	public function sort(string $orderBy = null, string $orderDir = null) {
		if (!is_null($orderBy) && !is_null($orderDir)) {
			$this->orderBy($orderBy, $orderDir);
		}
	}
}
