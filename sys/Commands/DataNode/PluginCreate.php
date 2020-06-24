<?php


namespace Environet\Sys\Commands\DataNode;

use Environet\Sys\Commands\BaseCommand;
use Environet\Sys\Plugins\PluginBuilder;

/**
 * Class PluginCreate
 *
 * Creates a new plugin configuration
 *
 * @package Environet\Sys\Commands\DataNode
 * @author  SRG Group <dev@srg.hu>
 */
class PluginCreate extends BaseCommand {


	/**
	 * Run create plugin command.
	 *
	 * Creates and saves a configuration file under '/src/conf/plugins/configurations/'.
	 *
	 * @param $arguments
	 *
	 * @return int
	 * @uses \Environet\Sys\Plugins\PluginBuilder::createConfiguration()
	 * @uses \Environet\Sys\Plugins\PluginBuilder::serializeConfiguration()
	 */
	public function run($arguments): int {
		$configurationsPath = SRC_PATH . '/conf/plugins/configurations/';
		$configurationsRealPath = empty(getenv('ENVIRONET_PLUGIN_CONF_DIR')) ? $configurationsPath : getenv('ENVIRONET_PLUGIN_CONF_DIR') . '/configurations';
		$pluginBuilder = new PluginBuilder();
		$pluginBuilder->createConfiguration($this->console);

		$configuration = $pluginBuilder->serializeConfiguration();

		while (true) {
			$this->console->writeLine("Your configuration will be saved into '" . $configurationsRealPath . "'. Enter a filename you wish to save it as.");
			$filename = $this->console->ask("Configuration name:");

			if (file_exists($configurationsPath . $filename)) {
				if (!$this->console->askYesNo("The configuration [$filename] already exists, do you want to overwrite it?", true)) {
					continue;
				}
			}
			break;
		}

		if (!is_dir($configurationsPath)) {
			mkdir($configurationsPath, 0755, true);
		}
		file_put_contents($configurationsPath . $filename, $configuration);

		$this->console->writeLine("The configuration has been saved to " . $configurationsRealPath . '/' . $filename);

		return 0;
	}


}
