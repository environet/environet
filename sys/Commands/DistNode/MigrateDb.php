<?php


namespace Environet\Sys\Commands\DistNode;

use Environet\Sys\Commands\Console;
use Environet\Sys\General\Db\Connection;
use Environet\Sys\General\Exceptions\InvalidConfigurationException;
use Environet\Sys\General\Exceptions\QueryException;
use PDO;

/**
 * Class MigrateDb
 *
 * Initializes a clean database copy for a new distribution node
 *
 * @package Environet\Sys\Commands\DistNode
 * @author  SRG Group <dev@srg.hu>
 */
class MigrateDb extends DbCommand {

	/**
	 * @var Connection|string|null
	 */
	protected $connection;


	/**
	 * MigrateDb constructor.
	 *
	 * @param Console $console
	 *
	 * @throws InvalidConfigurationException
	 */
	public function __construct(Console $console) {
		parent::__construct($console);
		$this->connection = Connection::getInstance();
	}


	/**
	 * Run the initialization command.
	 *
	 * The command does the following steps:
	 *
	 * @param array $arguments
	 *
	 * @return int
	 */
	public function run($arguments): int {
		$migrations = [
			'createCrudPermissions',
			'createDataAclTables',
			'createUploadPermissions',
			'createRiverbankPermissions'
		];

		//Run each migrations, and log results
		foreach ($migrations as $migration) {
			$output = [];
			$exitCode = $this->{$migration}($output);

			if ($exitCode === -1) {
				//Already migrated
				$this->console->writeLine("$migration: Already migrated", Console::COLOR_YELLOW);
			} elseif ($exitCode > 0) {
				//Error during migration
				$this->console->writeLine("$migration: Errors:", Console::COLOR_RED);
				foreach ($output as $item) {
					$this->console->writeLine("$item");
				}
			} else {
				//Success
				$this->console->writeLine("$migration: Done", Console::COLOR_GREEN);
			}
			$this->console->writeLineBreak();
		}

		return $exitCode;
	}


	/**
	 * Create CRUD permissions
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function createCrudPermissions(array &$output): int {
		//Check if a permission is already added
		$count = $this->connection->runQuery(
			'SELECT COUNT(*) FROM public.permissions WHERE name = :loginPermission',
			['loginPermission' => 'admin.login']
		)->fetch(PDO::FETCH_COLUMN);
		if ($count) {
			return -1;
		}
		$schemaPath = SRC_PATH . '/database/create_crud_permissions.sql';
		return $this->runSqlFile($schemaPath, $output);
	}


	/**
	 * Create acl tables
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function createDataAclTables(array &$output): int {
		//Check if a table already exists
		$count = $this->connection->runQuery(
			'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = :tableName;',
			['tableName' => 'measurement_access_rules']
		)->fetch(PDO::FETCH_COLUMN);
		if ($count) {
			return -1;
		}
		$schemaPath = SRC_PATH . '/database/data_acl.sql';
		return $this->runSqlFile($schemaPath, $output);
	}


	/**
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function createUploadPermissions(array &$output): int {
		//Check if a permission is already added
		$count = $this->connection->runQuery(
			'SELECT COUNT(*) FROM public.permissions WHERE name = :uploadPermission',
			['uploadPermission' => 'admin.missingData.upload']
		)->fetch(PDO::FETCH_COLUMN);
		if ($count) {
			return -1;
		}
		$schemaPath = SRC_PATH . '/database/create_upload_permissions.sql';
		return $this->runSqlFile($schemaPath, $output);
	}
	
	/**
	 * Create riverbank permissions
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function createRiverbankPermissions(array &$output): int {

		$count = $this->connection->runQuery(
			'SELECT COUNT(*) FROM public.permissions WHERE name = :riverbankPermission',
			['riverbankPermission' => 'admin.hydro.riverbanks.read']
		)->fetch(PDO::FETCH_COLUMN);
		if ($count) {
			return -1;
		}
		$schemaPath = SRC_PATH . '/database/create_riverbank_permissions.sql';
		return $this->runSqlFile($schemaPath, $output);
	}

}
