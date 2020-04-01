<?php


namespace Environet\Sys\Commands\DataNode;

use Environet\Sys\Commands\BaseCommand;
use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\PluginBuilder;

/**
 * Class PluginRun
 *
 * Runs a plugin with the provided configuration file.
 *
 * @package Environet\Sys\Commands\DataNode
 * @author  SRG Group <dev@srg.hu>
 */
class PluginRun extends BaseCommand {


	/**
	 * Run plugin command.
	 *
	 * Parses the plugin configuration from the fourth argument and runs the selected plugin.
	 *
	 * @param $arguments
	 *
	 * @return int
	 * @see PluginBuilder::loadFromConfiguration()
	 */
	public function run($arguments): int {
		$configurationsPath = SRC_PATH . '/conf/plugins/configurations/';
		$configFile = $arguments[3] ?? null;
		if (!$configFile) {
			$this->console->writeLine("Configuration file name is required", Console::COLOR_RED);

			return 1;
		}

		if (!file_exists($configurationsPath . $configFile)) {
			$this->console->writeLine("Configuration file does not exist", Console::COLOR_RED);

			return 1;
		}
		$configuration = parse_ini_file($configurationsPath . $configFile, true);

		$pluginBuilder = new PluginBuilder();
		$plugin = $pluginBuilder->loadFromConfiguration($configuration);

		echo $plugin->run($this->console);

		return 0;
	}


}
