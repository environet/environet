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
			'createRiverbankPermissions',
			'createResultUniqueIndexesDeleteDuplicates',
			'renameDataProviderPermissions',
			'addIsActiveColumns'
		];
		ini_set('memory_limit', - 1);

		//Run each migrations, and log results
		$mainExitCode = 0;
		foreach ($migrations as $migration) {
			$output = [];
			$exitCode = $this->{$migration}($output);

			if ($exitCode === - 1) {
				//Already migrated
				$this->console->writeLine("$migration: Already migrated", Console::COLOR_YELLOW);
			} elseif ($exitCode > 0) {
				//Error during migration
				$this->console->writeLine("$migration: Errors:", Console::COLOR_RED);
				foreach ($output as $item) {
					$this->console->writeLine("$item");
				}
				$mainExitCode = 1;
			} else {
				//Success
				$this->console->writeLine("$migration: Done", Console::COLOR_GREEN);
			}
			$this->console->writeLineBreak();
		}

		return $mainExitCode;
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
			return - 1;
		}
		$schemaPath = SRC_PATH . '/database/create_crud_permissions.sql';

		return $this->runSqlFile($schemaPath, $output);
	}


	/**
	 * Create acl tables
	 *
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
			return - 1;
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
			return - 1;
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
			return - 1;
		}
		$schemaPath = SRC_PATH . '/database/create_riverbank_permissions.sql';

		return $this->runSqlFile($schemaPath, $output);
	}


	/**
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function createResultUniqueIndexesDeleteDuplicates(array &$output): int {

		$return = -1;
		foreach (['hydro', 'meteo'] as $type) {
			//Find duplicates for type
			$tsColumn = ($type == 'meteo' ? 'meteo_' : '') . 'time_seriesid';
			$resultTable = "{$type}_result";
			$indexName = "{$type}_unique_time_value";

			if (!$this->checkIndex($resultTable, $indexName)) {
				//If index is not yet created, delete duplicates first, and after it add unique index
				$return = 0;
				$this->console->writeLine("Clean duplicated $type results...");
				$uniqueValues = [];
				$deleteIds = [];

				$results = $this->connection->runQuery(
					"SELECT * FROM public.$resultTable ORDER BY $tsColumn DESC, time DESC, value DESC, is_forecast DESC, created_at DESC",
					[]
				)->fetchAll();
				$this->console->writeLine(sprintf("Fount %d records", count($results)));
				foreach ($results as $result) {
					$uniqueValue = $result[$tsColumn] . '_' . $result['time'] . '_' . $result['value'] . '_' . $result['is_forecast'];
					if (in_array($uniqueValue, $uniqueValues)) {
						$deleteIds[] = $result['id'];
					} else {
						if (count($uniqueValues) > 50) {
							array_shift($uniqueValues);
						}
						$uniqueValues[] = $uniqueValue;
					}
				}

				if ($deleteIds) {
					//Delete ids
					$this->console->writeLine(sprintf('Delete %d duplicated rows for type %s', count($deleteIds), $type));
					$this->connection->runQuery('DELETE FROM public.'.$type.'_result WHERE id IN(' . implode(',', $deleteIds) . ')', []);
				}

				//Add index
				$this->console->write("Create unique index...");
				$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS $indexName ON $resultTable ($tsColumn,time,value,is_forecast)", []);
				$this->console->writeLine("done\n");
			}
		}

		return $return;
	}


	/**
	 * Create riverbank permissions
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function renameDataProviderPermissions(array &$output): int {

		$count = $this->connection->runQuery(
			'SELECT COUNT(*) FROM public.permissions WHERE name like :providerPermission',
			['providerPermission' => 'admin.providers%']
		)->fetch(PDO::FETCH_COLUMN);
		if ($count === 0) {
			return - 1;
		}
		$schemaPath = SRC_PATH . '/database/rename_data_provider_permissions.sql';

		return $this->runSqlFile($schemaPath, $output);
	}


	/**
	 * Add is_active column
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function addIsActiveColumns(array &$output): int {
		$return = -1;

		$count = $this->connection->runQuery(
			'SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_NAME = :tableName AND column_name = :columnName;',
			['tableName' => 'hydropoint', 'columnName' => 'is_active']
		)->fetch(PDO::FETCH_COLUMN);
		if (!$count) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE hydropoint ADD COLUMN is_active boolean DEFAULT true NOT NULL", []);
		}

		$count = $this->connection->runQuery(
			'SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_NAME = :tableName AND column_name = :columnName;',
			['tableName' => 'meteopoint', 'columnName' => 'is_active']
		)->fetch(PDO::FETCH_COLUMN);
		if (!$count) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE meteopoint ADD COLUMN is_active boolean DEFAULT true NOT NULL", []);
		}

		return $return;
	}


	/**
	 * Check if index exists in table
	 *
	 * @param string $tableName
	 * @param string $indexName
	 *
	 * @return bool
	 * @throws QueryException
	 */
	private function checkIndex(string $tableName, string $indexName) {
		$count = $this->connection->runQuery("select COUNT(*)
			from pg_class t, pg_class i, pg_index ix, pg_attribute a
			where t.oid = ix.indrelid and i.oid = ix.indexrelid and a.attrelid = t.oid and a.attnum = ANY(ix.indkey) and t.relkind = 'r' 
			  and t.relname like '$tableName' and i.relname = '$indexName'", [])->fetchColumn();
		return ((int) $count) > 0;
	}


}
