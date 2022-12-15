<?php


namespace Environet\Sys\Commands\DistNode;

use Environet\Sys\Commands\Console;
use Environet\Sys\General\Db\Connection;
use Environet\Sys\General\Exceptions\InvalidConfigurationException;
use Environet\Sys\General\Exceptions\QueryException;
use PDO;
use Throwable;

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
			'addIsActiveColumns',
			'removeMeteoPrefixes',
			'renameWaterbody',
			'uniqueFieldsAndRenames',
			'warningLevels',
			'fixIndexesPointProperty',
			'addOutOfOrderColumns',
			'addObsoleteFlagForRecords',
			'resultTimeNullable',
			'riverBasins',
			'riverBasinNameIndex',
			'pointLastUpdated',
			'downloadLogs',
		];
		ini_set('memory_limit', - 1);

		try {
			$this->connection->runQuery("BEGIN TRANSACTION;", []);

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

			$this->connection->runQuery("COMMIT TRANSACTION;", []);
		} catch (Throwable $exception) {
			$this->connection->runQuery("ROLLBACK TRANSACTION;", []);
			throw $exception;
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

		$return = - 1;
		foreach (['hydro', 'meteo'] as $type) {
			//Find duplicates for type
			$tsColumn = 'time_seriesid';
			$resultTable = "{$type}_result";
			$indexName = "{$type}_unique_time_value";

			if ($type === 'meteo') {
				$tsColumn = $this->checkColumn($resultTable, $tsColumn) ? $tsColumn : 'meteo_' . $tsColumn;
			}

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
					$this->connection->runQuery('DELETE FROM public.' . $type . '_result WHERE id IN(' . implode(',', $deleteIds) . ')', []);
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
		$return = - 1;

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
	 * Add is_active column
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function removeMeteoPrefixes(array &$output): int {
		$return = - 1;


		if ($this->checkColumn('meteo_result', 'meteo_time_seriesid')) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE meteo_result RENAME COLUMN meteo_time_seriesid TO time_seriesid;", []);
		}
		$meteoResultColumnData = $this->getColumnData('meteo_result', 'value');
		if ($meteoResultColumnData && $meteoResultColumnData['data_type'] !== 'numeric') {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE meteo_result ALTER COLUMN value TYPE numeric(20,10);", []);
		}

		if ($this->checkColumn('meteo_time_series', 'meteopointid')) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE meteo_time_series RENAME COLUMN meteopointid TO mpointid;", []);
		}
		if ($this->checkColumn('meteo_time_series', 'meteo_observed_propertyid')) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE meteo_time_series RENAME COLUMN meteo_observed_propertyid TO observed_propertyid;", []);
		}

		if ($this->checkColumn('meteopoint', 'meteostation_classificationid')) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE meteopoint RENAME COLUMN meteostation_classificationid TO station_classificationid;", []);
		}

		if ($this->checkColumn('meteopoint_observed_property', 'meteo_observed_propertyid')) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE meteopoint_observed_property RENAME COLUMN meteo_observed_propertyid TO observed_propertyid;", []);
		}
		if ($this->checkColumn('meteopoint_observed_property', 'meteopointid')) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE meteopoint_observed_property RENAME COLUMN meteopointid TO mpointid;", []);
		}

		$hasMonitoringPointTable = $this->connection->runQuery(
			'SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_NAME = :tableName',
			['tableName' => 'monitoring_point']
		)->fetch(PDO::FETCH_COLUMN);
		if ($hasMonitoringPointTable) {
			$return = 0;
			$this->connection->runQuery("DROP TABLE IF EXISTS monitoring_point;", []);
		}

		return $return;
	}


	/**
	 * Rename waterbody to river
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function renameWaterbody(array &$output): int {
		$return = - 1;


		if ($this->checkColumn('hydropoint', 'waterbodyeuropean_river_code')) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE hydropoint RENAME COLUMN waterbodyeuropean_river_code TO river_european_river_code;", []);
		}

		if ($this->checkColumn('waterbody', 'european_river_code')) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE waterbody RENAME TO river;", []);
		}

		if ($this->checkIndex('river', 'waterbody_pkey')) {
			$return = 0;
			$this->connection->runQuery("ALTER INDEX waterbody_pkey RENAME TO river_pkey;", []);
		}

		if ($this->checkConstraint('hydropoint', 'hydropoint_waterbodyeuropean_river_code_fkey')) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE hydropoint DROP CONSTRAINT hydropoint_waterbodyeuropean_river_code_fkey;", []);
			$this->connection->runQuery("
				ALTER TABLE hydropoint 
				    ADD CONSTRAINT hydropoint_river_european_river_code_fkey FOREIGN KEY (river_european_river_code) REFERENCES public.river(european_river_code);
		    ", []);
		}

		$permissions = $this->connection->runQuery("SELECT * FROM permissions WHERE name LIKE '%waterbodies%';", [])->fetchAll();
		if (!empty($permissions)) {
			$return = 0;
			$this->connection->runQuery("UPDATE permissions SET name = REPLACE(name, 'waterbodies', 'rivers');", []);
		}

		$eventLogs = $this->connection->runQuery("SELECT * FROM event_logs WHERE event_type LIKE '%waterbody%';", [])->fetchAll();
		if (!empty($eventLogs)) {
			$return = 0;
			$this->connection->runQuery("UPDATE event_logs SET event_type = REPLACE(event_type, 'waterbody', 'river');", []);
		}

		return $return;
	}


	/**
	 * Add some unique indexes, and rename some fields
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function uniqueFieldsAndRenames(array &$output): int {
		$return = - 1;

		if ($this->checkColumn('hydropoint', 'river_european_river_code')) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE hydropoint RENAME COLUMN river_european_river_code TO eucd_riv;", []);
		}
		if ($this->checkColumn('river', 'european_river_code')) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE river RENAME COLUMN european_river_code TO eucd_riv;", []);
		}

		$operatorOtherInfoData = $this->getColumnData('operator', 'other_info');
		if ($operatorOtherInfoData && $operatorOtherInfoData['data_type'] !== 'text') {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE operator ALTER COLUMN other_info TYPE text;", []);
		}

		if (!$this->checkIndex('discharge_measurement_equipment', 'discharge_measurement_equipment_description_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS discharge_measurement_equipment_description_unique ON discharge_measurement_equipment (description)", []);
		}

		if (!$this->checkIndex('hydropoint', 'eucd_wgst_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS eucd_wgst_unique ON hydropoint (eucd_wgst)", []);
		}
		if (!$this->checkIndex('hydropoint', 'ncd_wgst_operator_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS ncd_wgst_operator_unique ON hydropoint (ncd_wgst, operatorid)", []);
		}
		if (!$this->checkIndex('meteopoint', 'eucd_pst_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS eucd_pst_unique ON meteopoint (eucd_pst)", []);
		}
		if (!$this->checkIndex('meteopoint', 'ncd_pst_operator_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS ncd_pst_operator_unique ON meteopoint (ncd_pst, operatorid)", []);
		}

		if (!$this->checkIndex('hydro_observed_property', 'hydro_observed_property_symbol_type_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS hydro_observed_property_symbol_type_unique ON hydro_observed_property (symbol, type)", []);
		}
		if (!$this->checkIndex('meteo_observed_property', 'meteo_observed_property_symbol_type_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS meteo_observed_property_symbol_type_unique ON meteo_observed_property (symbol, type)", []);
		}

		if (!$this->checkIndex('hydro_time_series', 'hydro_time_series_mpoint_property_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS hydro_time_series_mpoint_property_unique ON hydro_time_series (mpointid, observed_propertyid)", []);
		}
		if (!$this->checkIndex('meteo_time_series', 'meteo_time_series_mpoint_property_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS meteo_time_series_mpoint_property_unique ON meteo_time_series (mpointid, observed_propertyid)", []);
		}

		if (!$this->checkIndex('hydrostation_classification', 'hydrostation_classification_value_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS hydrostation_classification_value_unique ON hydrostation_classification (value)", []);
		}
		if (!$this->checkIndex('meteostation_classification', 'meteostation_classification_value_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS meteostation_classification_value_unique ON meteostation_classification (value)", []);
		}

		if (!$this->checkIndex('groups', 'groups_name_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS groups_name_unique ON groups (name)", []);
		}
		if (!$this->checkIndex('operator', 'operator_name_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS operator_name_unique ON operator (name)", []);
		}
		if (!$this->checkIndex('permissions', 'permissions_name_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS permissions_name_unique ON permissions (name)", []);
		}
		if (!$this->checkIndex('river', 'river_cname_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS river_cname_unique ON river (cname)", []);
		}
		if (!$this->checkIndex('riverbank', 'riverbank_value_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS riverbank_value_unique ON riverbank (value)", []);
		}
		if (!$this->checkIndex('users', 'users_username_unique')) {
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS users_username_unique ON users (username)", []);
		}

		return $return;
	}


	/**
	 * Rename waterbody to river
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function warningLevels(array &$output): int {
		$return = - 1;

		if (!$this->checkTable('warning_level_groups')) {
			$return = 0;
			$this->connection->runQuery("CREATE SEQUENCE IF NOT EXISTS public.warning_level_group_id_seq START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;", []);
			$this->connection->runQuery("
				CREATE TABLE public.warning_level_groups (
				    id integer DEFAULT nextval('public.warning_level_group_id_seq'::regclass) NOT NULL,
				    name character varying(255) NOT NULL
				);
			", []);
			$this->connection->runQuery("CREATE UNIQUE INDEX warning_level_group_pkey ON public.warning_level_groups USING btree (id)", []);
		}

		if ($this->checkTable('warning_level') && !$this->checkTable('warning_levels')) {
			$return = 0;
			$this->connection->runQuery("DROP TABLE IF EXISTS warning_level;", []);
			$this->connection->runQuery("CREATE SEQUENCE IF NOT EXISTS public.warning_level_id_seq START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;", []);
			$this->connection->runQuery("DROP SEQUENCE IF EXISTS public.warning_level_warning_level_seq;", []);
			$this->connection->runQuery("
				CREATE TABLE public.warning_levels (
				    id integer DEFAULT nextval('public.warning_level_id_seq'::regclass) NOT NULL,
				    operatorid integer NOT NULL,
				    warning_level_groupid integer NOT NULL,
				    color character varying(6),
				    short_description character varying(512) NOT NULL,
				    long_description text,
				    is_inclusive bool NOT NULL DEFAULT true
				);
			", []);
			$this->connection->runQuery("ALTER TABLE ONLY public.warning_levels ADD CONSTRAINT warning_level_pkey PRIMARY KEY (id);", []);
			$this->connection->runQuery(
				"ALTER TABLE ONLY public.warning_levels ADD CONSTRAINT warning_levels_operatorid_fkey FOREIGN KEY (operatorid) REFERENCES public.operator(id);",
				[]
			);
			$this->connection->runQuery(
				"ALTER TABLE ONLY public.warning_levels ADD CONSTRAINT warning_levels_warning_level_groupid_fkey FOREIGN KEY (warning_level_groupid) REFERENCES public.warning_level_groups(id);",
				[]
			);
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS operatorid_short_description_unique ON warning_levels (operatorid,short_description)", []);
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS operatorid_color_unique ON warning_levels (operatorid,color)", []);
		}

		if (!$this->checkTable('warning_level_hydropoint')) {
			$return = 0;
			$this->connection->runQuery("
				CREATE TABLE public.warning_level_hydropoint (
				    mpointid integer NOT NULL,
				    observed_propertyid integer NOT NULL,
				    warning_levelid integer NOT NULL,
				    value numeric(20,10) NOT NULL
				);
			", []);
			$this->connection->runQuery(
				"ALTER TABLE ONLY public.warning_level_hydropoint ADD CONSTRAINT warning_level_hydropoint_mpointid_fkey FOREIGN KEY (mpointid) REFERENCES public.hydropoint(id);",
				[]
			);
			$this->connection->runQuery(
				"ALTER TABLE ONLY public.warning_level_hydropoint ADD CONSTRAINT warning_level_hydropoint_observed_propertyid_fkey FOREIGN KEY (observed_propertyid) REFERENCES public.hydro_observed_property(id);",
				[]
			);
			$this->connection->runQuery(
				"ALTER TABLE ONLY public.warning_level_hydropoint ADD CONSTRAINT warning_level_hydropoint_warning_levelid_fkey FOREIGN KEY (warning_levelid) REFERENCES public.warning_levels(id);",
				[]
			);
		}

		return $return;
	}


	/**
	 * Add some unique indexes, to hydropoint_observed_property and meteopoint_observed_property tables
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function fixIndexesPointProperty(array &$output): int {
		$return = - 1;

		if (!$this->checkIndex('hydropoint_observed_property', 'hydro_observed_property_mpoint_unique')) {
			$return = 0;
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS hydro_observed_property_mpoint_unique ON hydropoint_observed_property (observed_propertyid, mpointid)", []);
		}

		if (!$this->checkIndex('meteopoint_observed_property', 'meteo_observed_property_mpoint_unique')) {
			$return = 0;
			$this->connection->runQuery("CREATE UNIQUE INDEX IF NOT EXISTS meteo_observed_property_mpoint_unique ON meteopoint_observed_property (observed_propertyid, mpointid)", []);
		}

		return $return;
	}


	/**
	 * Adds a temporary out of order column for hydropoint and meteopoint
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function addOutOfOrderColumns(array &$output): int {
		$return = - 1;

		if (!$this->checkColumn('hydropoint', 'is_out_of_order')) {
			$return = 0;
			$this->connection->runQuery('ALTER TABLE hydropoint ADD COLUMN is_out_of_order boolean DEFAULT false NOT NULL', []);
		}

		if (!$this->checkColumn('meteopoint', 'is_out_of_order')) {
			$return = 0;
			$this->connection->runQuery('ALTER TABLE meteopoint ADD COLUMN is_out_of_order boolean DEFAULT false NOT NULL', []);
		}

		return $return;
	}


	/**
	 * Adds a flag for outdated result data in hydro_result and meteo_result
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function addObsoleteFlagForRecords(array &$output): int {
		$return = - 1;

		if (!$this->checkColumn('hydro_result', 'is_obsolete')) {
			$return = 0;
			$this->connection->runQuery('ALTER TABLE hydro_result ADD COLUMN is_obsolete boolean DEFAULT false NOT NULL', []);
		}

		if (!$this->checkColumn('meteo_result', 'is_obsolete')) {
			$return = 0;
			$this->connection->runQuery('ALTER TABLE meteo_result ADD COLUMN is_obsolete boolean DEFAULT false NOT NULL', []);
		}

		return $return;
	}


	/**
	 * Update time series table, result time must be nullable
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function resultTimeNullable(array &$output): int {
		$return = - 1;

		foreach (['hydro', 'meteo'] as $type) {
			$columnData = $this->getColumnData("{$type}_time_series", 'result_time');
			if (isset($columnData['is_nullable']) && $columnData['is_nullable'] === 'NO') {
				$return = 0;
				$this->connection->runQuery("ALTER TABLE {$type}_time_series ALTER COLUMN result_time DROP NOT NULL;", []);
			}
		}

		return $return;
	}


	/**
	 * River basins crud
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function riverBasins(array &$output): int {
		$return = - 1;

		if (!$this->checkTable('river_basin')) {
			$return = 0;

			$this->connection->runQuery("
				CREATE TABLE river_basin (
	                id integer NOT NULL,
	                name varchar(255) NOT NULL,
	                PRIMARY KEY (id)
				);
			", []);
			$this->connection->runQuery("CREATE UNIQUE INDEX river_basin_id ON public.river_basin USING btree (id)", []);
			$this->connection->runQuery("CREATE UNIQUE INDEX river_basin_name ON public.river_basin USING btree (name)", []);
		}

		if (!$this->checkColumn('hydropoint', 'river_basin_id')) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE hydropoint ADD COLUMN river_basin_id integer DEFAULT NULL", []);
			$this->connection->runQuery(
				"ALTER TABLE public.hydropoint ADD CONSTRAINT hydropoint_river_basin_id_fkey FOREIGN KEY (river_basin_id) REFERENCES public.river_basin(id);",
				[]
			);

			if ($this->checkColumn('hydropoint', 'river_basin')) {
				$this->connection->runQuery("ALTER TABLE hydropoint DROP COLUMN river_basin", []);
			}
		}

		if (!$this->checkColumn('meteopoint', 'river_basin_id')) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE meteopoint ADD COLUMN river_basin_id integer DEFAULT NULL", []);
			$this->connection->runQuery(
				"ALTER TABLE public.meteopoint ADD CONSTRAINT meteopoint_river_basin_id_fkey FOREIGN KEY (river_basin_id) REFERENCES public.river_basin(id);",
				[]
			);

			if ($this->checkColumn('meteopoint', 'river_basin')) {
				$this->connection->runQuery("ALTER TABLE meteopoint DROP COLUMN river_basin", []);
			}
		}


		$count = $this->connection->runQuery(
			'SELECT COUNT(*) FROM public.permissions WHERE name = :riverbankPermission',
			['riverbankPermission' => 'admin.hydro.riverbasins.read']
		)->fetch(PDO::FETCH_COLUMN);
		if (!$count) {
			$return = 0;
			$schemaPath = SRC_PATH . '/database/create_riverbasin_permissions.sql';

			return $this->runSqlFile($schemaPath, $output);
		}

		return $return;
	}


	/**
	 * River basins crud
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function riverBasinNameIndex(array &$output): int {
		$return = - 1;

		if ($this->checkTable('river_basin') && !$this->checkIndex('river_basin', 'river_basin_name')) {
			$return = 0;

			$this->connection->runQuery("CREATE UNIQUE INDEX river_basin_name ON public.river_basin USING btree (name)", []);
		}

		return $return;
	}


	/**
	 * River basins crud
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function pointLastUpdated(array &$output): int {
		$return = - 1;

		if (!$this->checkColumn('hydropoint', 'last_updated_at')) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE hydropoint 
    			ADD COLUMN last_updated_at timestamp DEFAULT NULL,
    			ADD COLUMN last_updated_by integer DEFAULT NULL
            ", []);
		}

		if (!$this->checkColumn('meteopoint', 'last_updated_at')) {
			$return = 0;
			$this->connection->runQuery("ALTER TABLE meteopoint 
    			ADD COLUMN last_updated_at timestamp DEFAULT NULL,
    			ADD COLUMN last_updated_by integer DEFAULT NULL
            ", []);
		}


		return $return;
	}


	/**
	 * Create download logs table
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws QueryException
	 */
	private function downloadLogs(array &$output): int {
		$return = - 1;

		if (!$this->checkTable('download_logs')) {
			$return = 0;
			$this->connection->runQuery("CREATE SEQUENCE IF NOT EXISTS public.download_logs_id_seq START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;", []);
			$this->connection->runQuery("
				CREATE TABLE public.download_logs (
					id integer NOT NULL DEFAULT nextval('download_logs_id_seq'::regclass),
					created_at timestamp NOT NULL,
					user_id integer,
					request_attributes json,
					request_ip varchar(15),
					response_status int4 NOT NULL,
					response_size int4,
					execution_time int4,
					error_code int4,
					param_type varchar,
					param_start date,
					param_end date,
					param_country _varchar,
					param_symbol _varchar,
					param_point _varchar
				);
			", []);

			$this->connection->runQuery("ALTER TABLE ONLY public.download_logs ADD CONSTRAINT download_log_pkey PRIMARY KEY (id);", []);
			$this->connection->runQuery(
				"ALTER TABLE ONLY public.download_logs ADD CONSTRAINT download_logs_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);",
				[]
			);
		}

		return $return;
	}


	/**
	 * @param string $tableName
	 *
	 * @return bool
	 * @throws QueryException
	 */
	private function checkTable(string $tableName) {
		$count = $this->connection->runQuery(
			'SELECT COUNT(*) FROM information_schema.tables WHERE table_name = :tableName;',
			['tableName' => $tableName]
		)->fetch(PDO::FETCH_COLUMN);

		return (bool) $count;
	}


	/**
	 * @param string $tableName
	 * @param string $columnName
	 *
	 * @return bool
	 * @throws QueryException
	 */
	private function checkColumn(string $tableName, string $columnName) {
		$count = $this->connection->runQuery(
			'SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_NAME = :tableName AND column_name = :columnName;',
			['tableName' => $tableName, 'columnName' => $columnName]
		)->fetch(PDO::FETCH_COLUMN);

		return (bool) $count;
	}


	/**
	 * @param string $tableName
	 * @param string $columnName
	 *
	 * @return mixed|null
	 * @throws QueryException
	 */
	private function getColumnData(string $tableName, string $columnName) {
		$data = $this->connection->runQuery(
			'SELECT * FROM information_schema.columns WHERE TABLE_NAME = :tableName AND column_name = :columnName;',
			['tableName' => $tableName, 'columnName' => $columnName]
		)->fetch(PDO::FETCH_ASSOC);

		return $data ?: null;
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


	/**
	 * Check if constraint exists in table
	 *
	 * @param string $tableName
	 * @param string $constraintName
	 *
	 * @return bool
	 * @throws QueryException
	 */
	private function checkConstraint(string $tableName, string $constraintName) {
		$count = $this->connection->runQuery("select COUNT(*)
			from pg_catalog.pg_constraint con
			INNER JOIN pg_catalog.pg_class rel ON rel.oid = con.conrelid
			INNER JOIN pg_catalog.pg_namespace nsp ON nsp.oid = connamespace
			where rel.relname like '$tableName' and con.conname = '$constraintName'", [])->fetchColumn();

		return ((int) $count) > 0;
	}


}
