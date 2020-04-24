<?php


namespace Environet\Sys\General\Db\Query;

use Environet\Sys\General\Db\Connection;
use Environet\Sys\General\Exceptions\QueryException;
use PDO;

/**
 * Class Query
 *
 * Base class of query types. It provides common functionalities for select, update, insert and delete query classes and the possibility to run raw queries.
 *
 * @package Environet\Sys\General\Db\Query
 * @author  SRG Group <dev@srg.hu>
 */
class Query {

	const OPERATOR_AND = 'AND';
	const OPERATOR_OR  = 'OR';

	const DIR_ASC  = 'ASC';
	const DIR_DESC = 'DESC';

	const JOIN_INNER = 'INNER';
	const JOIN_OUTER = 'OUTER';
	const JOIN_LEFT  = 'LEFT';
	const JOIN_RIGHT = 'RIGHT';

	const FETCH_FIRST  = 0b00000001;
	const FETCH_COUNT  = 0b00000010;
	const FETCH_COLUMN = 0b00000100;
	const KEY_BY_ID    = 0b00001000;
	const RETURN_ID    = 0b00010000;
	const RETURN_BOOL  = 0b00100000;

	/**
	 * The connection class
	 * @var Connection|string
	 */
	protected $connection;

	/**
	 * Table of the query
	 * @var array
	 */
	protected $table;

	/**
	 * Parameter bindings
	 * @var array
	 */
	protected $parameters = [];

	/**
	 * @var string
	 */
	protected $rawQuery = null;


	/**
	 * Query constructor.
	 * Creates or gets the connection instance
	 */
	public function __construct() {
		$this->connection = Connection::getInstance();
	}


	/**
	 * Set the table on where the query will run
	 *
	 * @param string $table
	 *
	 * @return self
	 */
	public function table(string $table): self {
		$this->table = $table;

		return $this;
	}


	/**
	 * Get parameter bindings
	 *
	 * @return array
	 */
	public function getParameters(): array {
		return $this->parameters;
	}


	/**
	 * Set multiple parameter bindings. It clear all previously attached data in parameters array
	 *
	 * @param array $parameters
	 *
	 * @return self
	 */
	public function setParameters(array $parameters = []): self {
		$this->parameters = $parameters;

		return $this;
	}


	/**
	 * Reset the array of parameters bindings
	 *
	 * @return self
	 */
	public function resetParameters(): self {
		$this->parameters = [];

		return $this;
	}


	/**
	 * Add a single parameter binding by key and value
	 *
	 * @param string $key   The name of the parameter
	 * @param mixed  $value The value of the parameter
	 *
	 * @return self
	 */
	public function addParameter($key, $value): self {
		$this->parameters[$key] = $value;

		return $this;
	}


	/**
	 * Add multiple parameters. It does'nt reset the parameters array before adding the new ones
	 *
	 * @param array $parameters
	 *
	 * @return self
	 * @uses \Environet\Sys\General\Db\Query\Query::addParameter()
	 */
	public function addParameters(array $parameters = []): self {
		foreach ($parameters as $key => $value) {
			$this->addParameter($key, $value);
		}

		return $this;
	}


	/**
	 * Run the query.
	 *
	 * First it runs some easy check on the properties, and if is there any error, it won't run the query, but throws and exception
	 * After it it will build the query string based on the properties, and set the parameters bindings.
	 * It returns the fetchAll result of the statement.
	 *
	 * @param null $flags Bitmask for some other options of the execution and fetching
	 *
	 * @return array|int
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Query::validateQuery()
	 * @uses \Environet\Sys\General\Db\Query\Query::buildQuery()
	 * @uses \Environet\Sys\General\Db\Connection::runQuery()
	 */
	public function run($flags = null) {
		if (!$this->validateQuery()) {
			throw new QueryException('Invalid query properties');
		}

		$this->connection->runQuery("SET intervalstyle = 'iso_8601'", []);

		$statement = $this->connection->runQuery($this->buildQuery(), $this->parameters);

		if ($flags & self::RETURN_BOOL) {
			// First we've to check the bool flag, because the last insert id is not always defined, and it can cause errors
			return true;
		} elseif ($flags & self::RETURN_ID) {
			// Return the last inserted id
			if (($lastInsertId = $this->connection->pdo->lastInsertId())) {
				return $lastInsertId;
			}
			throw new QueryException('Error while getting last insert id: ' . $statement->errorCode() . ': ' . ($statement->errorInfo()[2] ?? null));
		}

		if ($flags & self::FETCH_COUNT) {
			$response = $statement->fetch();

			return !empty($response) ? (int) reset($response) : null;
		}
		if ($flags & self::FETCH_FIRST) {
			// Fetch only one result
			return $statement->fetch(PDO::FETCH_NAMED) ?: null;
		}

		if ($flags & self::FETCH_COLUMN) {
			$results = $statement->fetchAll(PDO::FETCH_COLUMN) ?: [];
		} else {
			// Get results with a named fetch
			$results = $statement->fetchAll(PDO::FETCH_NAMED) ?: [];
		}

		// If the key-by-id flag is defined, reconstruct the array with the id keys. The 'id' property name is fixed.
		if ($flags & self::KEY_BY_ID) {
			$newResults = [];
			foreach ($results as $key => $result) {
				if (!isset($result['id'])) {
					// Id not found in result array
					throw new QueryException("Id for result number $key not found");
				}
				// Add to new array with the id key
				$newResults[$result['id']] = $result;
			}
			$results = $newResults;
		}

		return $results;
	}


	/**
	 * Validate the query properties.
	 * The base query only checks if the table is set.
	 *
	 * @return bool
	 */
	protected function validateQuery(): bool {
		return !empty($this->table);
	}


	/**
	 * Set a raw query, if the query classes can't build it.
	 * It will be executed without any check and parsing.
	 *
	 * @param string $rawQuery
	 *
	 * @return self
	 */
	public function setRawQuery(string $rawQuery): self {
		$this->rawQuery = $rawQuery;

		return $this;
	}


	/**
	 * Build the query.
	 * The standard implementation of buildQuery is to run the raw query if it's set.
	 *
	 * @return mixed
	 */
	protected function buildQuery() {
		return $this->rawQuery;
	}


}
