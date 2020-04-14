<?php


namespace Environet\Sys\Commands\DistNode;

use Environet\Sys\Commands\Console;

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
	 * Run the initialization command.
	 *
	 * The command does the following steps:
	 *
	 * @param array $arguments
	 *
	 * @return int
	 */
	public function run($arguments): int {
		$output = [];
		$exitCode = $this->createCrudPermissions($output);
		if ($exitCode > 0) {
			echo implode("\n", $output);
			return $exitCode;
		}

		$this->console->writeLine('Crud permissions created successfully', Console::COLOR_GREEN);

		$exitCode = $this->addSubjectIdColumnToPermissionTables($output);
		if ($exitCode > 0) {
			echo implode("\n", $output);
			return $exitCode;
		}

		$this->console->writeLine('Crud permissions created successfully', Console::COLOR_GREEN);

		return $exitCode;
	}


	private function createCrudPermissions(array &$output): int {
		$schemaPath = SRC_PATH . '/database/create_crud_permissions.sql';
		return $this->runSqlFile($schemaPath, $output);
	}


	private function addSubjectIdColumnToPermissionTables(array &$output): int {
		$schemaPath = SRC_PATH . '/database/add_subject_id_permissions.sql';
		return $this->runSqlFile($schemaPath, $output);
	}


}
