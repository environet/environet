<?php


namespace Environet\Sys\Commands\DistNode;

use Environet\Sys\Commands\Console;
use Environet\Sys\Commands\Exceptions\CommandException;
use Environet\Sys\Config;

/**
 * Class ExportDb
 *
 * Exports db content
 *
 * @package Environet\Sys\Commands\DistNode
 * @author  SRG Group <dev@srg.hu>
 */
class ExportDb extends DbCommand {


	/**
	 * Run the initialization command.
	 *
	 * The command does the following steps:
	 * 1. Imports the database schema via {@see InitDb::importSchema()}.
	 * 2. Imports a copy of clean starting data via {@see InitDb::importData()}.
	 * 3. Prompts the user for an administrator username and password to use in the future. The password has to be confirmed.
	 * 4. Creates the administrator account with the provided data.
	 *
	 * @param array $arguments
	 *
	 * @return int
	 * @throws CommandException
	 * @uses \Environet\Sys\Commands\DistNode\InitDb::importSchema()
	 * @uses \Environet\Sys\Commands\DistNode\InitDb::importData()
	 */
	public function run($arguments): int {
		$output = [];

		$file = SRC_PATH . '/data/export/export_' . time() . '.sql';
		if (!is_dir(dirname($file))) {
			mkdir(dirname($file), 0755, true);
		}

		$config = Config::getInstance();
		$host = $config->getDatabaseHost();
		$user = $config->getDatabaseUser();
		$pass = $config->getDatabasePass();
		$db = $config->getDatabaseDatabase();
		$port = $config->getDatabasePort();

		$exitCode = 0;
		// psql command will ask for password if we don't store it on a .pgpass file, so create this file temporarily
		$passFile = '~/.pgpass';
		exec("touch $passFile && chmod 600 $passFile &&  echo \"$host:$port:$db:$user:$pass\" > $passFile", $output, $exitCode);

		if ($exitCode === 0) {
			//Run psql import command, set ON_ERROR_STOP for valid exit codes

			exec("pg_dump --host=$host --username=$user --dbname=$db --port=$port --data-only --column-inserts >> $file", $output, $exitCode);
		}

		// Remove pgpass file
		exec("rm $passFile");

		$this->console->writeLine("Database successfully export to file: " . str_replace(SRC_PATH . '/', '', $file), Console::COLOR_GREEN);

		return $exitCode;
	}


}
