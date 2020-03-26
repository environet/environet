<?php


namespace Environet\Sys\Commands\DataNode;

use Environet\Sys\Commands\BaseCommand;
use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\PluginBuilder;

/**
 * Class PluginRun
 *
 * @package Environet\Sys\Commands\DataNode
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class PluginRun extends BaseCommand {


	/**
	 * @param $arguments
	 *
	 * @return int
	 */
	public function run($arguments): int {
		$configFile = $arguments[3] ?? null;
		if (!$configFile) {
			$this->console->writeLine("Configuration file name is required", Console::COLOR_RED);
			return 1;
		}

		if (!file_exists($configFile)) {
			$this->console->writeLine("Configuration file does not exist", Console::COLOR_RED);
			return 1;
		}

		$configuration = parse_ini_file($configurationsPath . $arguments, true);

		$pluginBuilder = new PluginBuilder();
		$plugin = $pluginBuilder->loadFromConfiguration($configuration);

		echo $plugin->run();

		return 0;
	}


}
