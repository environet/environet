<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\Config;
use Environet\Sys\General\Exceptions\InvalidConfigurationException;
use Environet\Sys\General\Exceptions\QueryException;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Class Connection
 *
 * Attempts to establish link to the PgSQL database server as configured, then breaks it down upon destruct.
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class Connection {

	/**
	 * @var PDO The connection instance.
	 */
	public $pdo = null;

	/**
	 * @var string The connection instance. It caches the instance, so it'll be created only once per request.
	 */
	protected static $instance = null;


	/**
	 * Connection constructor.
	 * Creates the database connection as well.
	 *
	 * @throws InvalidConfigurationException
	 * @uses \Environet\Sys\General\Db\Connection::connect()
	 */
	public function __construct() {
		// Create connection
		$this->connect();
	}


	/**
	 * Establishes PgSQL connection to the database, or report an error
	 * @throws InvalidConfigurationException
	 * @uses \Environet\Sys\Config::getSqlDsn()
	 * @uses PDO
	 */
	public function connect() {
		$dsn = Config::getInstance()->getSqlDsn();
		try {
			// Create a PostgreSQL database connection
			$this->pdo = new PDO($dsn);

			if (!$this->pdo) {
				throw new InvalidConfigurationException('Could not connect to database');
			}
		} catch (PDOException $e) {
			// Report error message
			en_debug("SQL operation failed - " . $e->getMessage());
			throw new InvalidConfigurationException('Could not connect to database');
		}
	}


	/**
	 * Close database connection (destroy the PDO object)
	 */
	public function __destruct() {
		$this->pdo = null;
	}


	/**
	 * Get the instance from the static property, or create new connection if the property is null.
	 * @return string|static
	 * @throws InvalidConfigurationException
	 */
	public static function getInstance() {
		if (is_null(self::$instance)) {
			self::$instance = new static();
		}

		return self::$instance;
	}


	/**
	 * Run a query on PDO with parameters
	 *
	 * @param string $queryString The raw SQL query string, optionally with parameters
	 * @param array  $parameters  Parameters to replace in query (key => value)
	 *
	 * @return PDOStatement
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Connection::parsePDOType()
	 */
	public function runQuery(string $queryString, array $parameters): PDOStatement {
		//Prepare the statement
		$statement = $this->pdo->prepare($queryString);

		//Bind parameters
		foreach ($parameters as $variableName => &$value) {
			//Prepend the : to the variable name, of not set yet
			if (substr($variableName, 0, 1) !== ':') {
				$variableName = ':' . $variableName;
			}

			//Bind param with detected PDO type
			$statement->bindParam($variableName, $value, $this->parsePDOType($value));
		}

		//Execute, and return the successful response
		if (($response = $statement->execute())) {
			return $statement;
		}

		//Error during query, throw an exception
		throw new QueryException('SQL query error with code ' . $statement->errorCode() . ': ' . ($statement->errorInfo()[2] ?? null));
	}


	/**
	 * Detect the PDO param type based on the value
	 *
	 * @param mixed $value
	 *
	 * @return int
	 */
	protected function parsePDOType($value) {
		switch (true) {
			case is_bool($value):
				return PDO::PARAM_BOOL;
			case is_int($value):
				return PDO::PARAM_INT;
			case is_null($value):
				return PDO::PARAM_NULL;
			default:
				return PDO::PARAM_STR;
		}
	}


}
