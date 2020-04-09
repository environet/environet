<?php


namespace Environet\Sys\Commands\DataNode;

use Environet\Sys\Commands\BaseCommand;
use Environet\Sys\Commands\Console;
use Environet\Sys\Config;

/**
 * Class Install
 *
 * Install data node, create local config file
 *
 * @package Environet\Sys\Commands\DataNode
 * @author  SRG Group <dev@srg.hu>
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
		$configFilePath = realpath(SRC_PATH.'/conf/conf.local.ini');

		if (file_exists($configFilePath)) {
			$continue = $this->console->askYesNo("Local configuration file already exists: $configFilePath. Do you want to overwrite it?", false);
			if (!$continue) {
				$this->console->writeLine("Abort install, keep existing config file", Console::COLOR_YELLOW);
				return 0;
			}
		}

		//Collect configurations in an array. OP_MODE is fixed, because it's the data node install
		$configArray = [
			'environet' => [
				'op_mode' => EN_OP_MODE_DATA,
			],
			'datanode'  => []
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

		$defautDistHost = Config::getInstance()->getDatanodeDistHost();
		//Ask database config options
		if ($defautDistHost) {
			$distHost = $this->console->askWithDefault("Enter the domain of distribution node:", $defautDistHost);
		} else {
			$distHost = $this->console->ask("Enter the domain of distribution node:");
		}

		//Add dist-host configuration to configArray
		$configArray['datanode']['dist_host'] = $distHost;

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
