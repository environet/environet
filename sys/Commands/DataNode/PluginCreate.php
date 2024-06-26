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
		$configurationsRealPath = empty(getenv('ENVIRONET_PLUGIN_CONF_DIR')) ? CONFIGURATION_PATH : getenv('ENVIRONET_PLUGIN_CONF_DIR') . '/configurations';
		$pluginBuilder = new PluginBuilder();
		$pluginBuilder->createConfiguration($this->console);

		$configuration = $pluginBuilder->serializeConfiguration();

		while (true) {
			$this->console->writeLine("Your configuration will be saved into '" . CONFIGURATION_PATH . "'. Enter a filename you wish to save it as.");
			$filename = $this->console->ask("Configuration name:");

			if (file_exists(rtrim(CONFIGURATION_PATH, '/')  . '/' . $filename)) {
				if (!$this->console->askYesNo("The configuration [$filename] already exists, do you want to overwrite it?", true)) {
					continue;
				}
			}
			break;
		}

		if (!is_dir(CONFIGURATION_PATH)) {
			mkdir(CONFIGURATION_PATH, 0755, true);
		}
		file_put_contents(rtrim(CONFIGURATION_PATH, '/')  . '/' . $filename, $configuration);

		$this->console->writeLine("The configuration has been saved to " . $configurationsRealPath . '/' . $filename);

		return 0;
	}


}
