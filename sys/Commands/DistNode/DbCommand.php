<?php


namespace Environet\Sys\Commands\DistNode;

use Environet\Sys\Commands\BaseCommand;
use Environet\Sys\Config;

/**
 * Class BaseCommand
 *
 * Base class for database console commands.
 *
 * @package Environet\Sys\Commands
 * @author  SRG Group <dev@srg.hu>
 */
abstract class DbCommand extends BaseCommand {


	/**
	 * Run the content of an SQL file with psql CLI command
	 *
	 * Get's the database configuration from {@see Config}, issues exec commands.
	 *
	 * @param string $file   SQL file
	 * @param array  $output Collect the output in this array
	 *
	 * @return int
	 */
	protected function runSqlFile(string $file, array &$output): int {
		// Get database parameters
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
			//Run psql import command
			exec("psql --host=$host --username=$user --dbname=$db --port=$port < $file 2>&1", $output, $exitCode);
		}

		// Remove pgpass file
		exec("rm $passFile");

		return $exitCode;
	}


}
