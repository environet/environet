<?php


namespace Environet\Sys\Commands\DistNode;

use Environet\Sys\Commands\BaseCommand;
use Environet\Sys\Commands\Console;
use PDO;
use PDOException;

/**
 * Class Install
 *
 * Install distribution node, create local config file
 *
 * @package Environet\Sys\Commands\DataNode
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class Install extends BaseCommand {


	/**
	 * Run installer
	 *
	 * @param $arguments
	 *
	 * @return int
	 */
	public function run($arguments): int {
		$configFilePath = SRC_PATH.'/conf/conf.local.ini';

		if (file_exists($configFilePath)) {
			$continue = $this->console->askYesNo("Local configuration file already exists: $configFilePath. Do you want to overwrite it?", false);
			if (!$continue) {
				$this->console->writeLine("Abort install, keep existing config file", Console::COLOR_YELLOW);
				return 0;
			}
		}

		startInstall:

		//Collect configurations in an array. OP_MODE is fixed, because it's the dist install
		$configArray = [
			'environet' => [
				'op_mode' => EN_OP_MODE_DIST,
			],
			'database'  => []
		];

		//Ask for timezone, and check the validity of it
		while (true) {
			$configArray['environet']['timezone'] = $this->console->ask("Enter a valid timezone name:");
			if (!in_array($configArray['environet']['timezone'], timezone_identifiers_list())) {
				$this->console->writeLine("Timezone is invalid! Try again", Console::COLOR_RED);
				continue;
			}
			break;
		}

		dbConfig:
		//Ask database config options
		$dbHost = $this->console->askWithDefault("Enter the database host:", "dist_database");
		$dbPort = $this->console->askWithDefault("Enter the database port:", 5432);
		$dbDatabase = $this->console->ask("Enter the database name:");
		$dbUser = $this->console->ask("Enter the database username:");
		$dbPass = $this->console->askHidden("Enter the database password:");

		//Check DB connection
		try {
			$dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbDatabase;user=$dbUser;password=$dbPass";
			new PDO($dsn);
		} catch (PDOException $e) {
			//Error while trying to connect, jump back to dbconfig
			$this->console->writeLine("Wrong database configuration: ".$e->getMessage());
			goto dbConfig;
		}

		//Add database configuration to configArray
		$configArray['database'] = [
			'host' => $dbHost,
			'port' => $dbPort,
			'database' => $dbDatabase,
			'user' => $dbUser,
			'pass' => $dbPass
		];

		//Build and save local ini file
		$iniContent = buildIni($configArray);
		if (!is_dir(dirname($configFilePath))) {
			mkdir(dirname($configFilePath), 0755, true);
		}
		file_put_contents($configFilePath, $iniContent);

		$this->console->writeLineBreak();
		$this->console->writeLine("The configuration has been saved to " . $configFilePath, Console::COLOR_GREEN);

		return 0;
	}


}
