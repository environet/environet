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
 * @package Environet\Sys\Commands\DistNode
 * @author  SRG Group <dev@srg.hu>
 */
class Install extends BaseCommand {


	/**
	 * Run installer
	 *
	 * Create a local configuration for the distribution node. Required data: Timezone, DB host, DB port, DB name, DB username, DB password.
	 *
	 * @param $arguments
	 *
	 * @return int
	 */
	public function run($arguments): int {
		
		$configDir = SRC_PATH . '/conf';
		
		$this->console->writeLine(
			'This is an Environet distribution node install script, which will generate a configuration file for this distribution node deployment',
			Console::COLOR_GREEN
		);
		
		$configFilePath = $configDir . '/conf.local.ini';
		$configFileRealpath = getenv('ENVIRONET_HOST_CONF_DIR', $configDir) . '/conf.local.ini';

		if (file_exists($configFilePath)) {
			$continue = $this->console->askYesNo("Local configuration file already exists: $configFileRealpath. Do you want to overwrite it?", false);
			if (!$continue) {
				$this->console->writeLine("Abort install, keep existing config file", Console::COLOR_YELLOW);

				return 0;
			}
		}

		// Collect configurations in an array. OP_MODE is fixed, because it's the dist install
		$configArray = [
			'environet' => [
				'op_mode' => EN_OP_MODE_DIST,
			],
			'database'  => []
		];
		
		// Ask for timezone, and check the validity of it
		if(!empty(getenv('ENVIRONET_TIMEZONE'))) {
			$configArray['environet']['timezone'] = getenv('ENVIRONET_TIMEZONE');
		} else {
			while (true) {
				$configArray['environet']['timezone'] = $this->console->askWithDefault("Enter a valid timezone name:", "UTC");
				if (!in_array($configArray['environet']['timezone'], timezone_identifiers_list())) {
					$this->console->writeLine("Timezone is invalid! Try again", Console::COLOR_RED);
					continue;
				}
				break;
			}
		}
		

		dbConfig:
		// Ask database config options if not set in env
		$dbHost = getenv('ENVIRONET_DB_HOST') ?: $this->console->askWithDefault("Enter the database host:", "dist_database");
		$dbPort = getenv('ENVIRONET_DB_PORT') ?: $this->console->askWithDefault("Enter the database port:", 5432);
		$dbDatabase = getenv('ENVIRONET_DB_NAME') ?: $this->console->ask("Enter the database name:");
		$dbUser = getenv('ENVIRONET_DB_USER') ?: $this->console->ask("Enter the database username:");
		$dbPass = getenv('ENVIRONET_DB_PASSWORD') ?: $this->console->askHidden("Enter the database password:");

		// Check DB connection
		try {
			$dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbDatabase;user=$dbUser;password=$dbPass";
			new PDO($dsn);
		} catch (PDOException $e) {
			// Error while trying to connect, jump back to dbconfig
			$this->console->writeLine("Wrong database configuration: " . $e->getMessage());
			goto dbConfig;
		}

		// Add database configuration to configArray
		$configArray['database'] = [
			'host'     => $dbHost,
			'port'     => $dbPort,
			'database' => $dbDatabase,
			'user'     => $dbUser,
			'pass'     => $dbPass
		];

		// Build and save local ini file
		$iniContent = buildIni($configArray);
		if (!is_dir(dirname($configFilePath))) {
			mkdir(dirname($configFilePath), 0755, true);
		}
		file_put_contents($configFilePath, $iniContent);

		$this->console->writeLineBreak();
		$this->console->writeLine("The distribution node configuration has been generated successfully", Console::COLOR_GREEN);
		$this->console->writeLine("The configuration has been saved to " . $configFileRealpath, Console::COLOR_GREEN);

		return 0;
	}


}
