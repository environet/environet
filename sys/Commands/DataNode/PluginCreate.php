<?php


namespace Environet\Sys\Commands\DataNode;

use Environet\Sys\Commands\BaseCommand;
use Environet\Sys\Plugins\PluginBuilder;

/**
 * Class PluginCreate
 *
 * @package Environet\Sys\Commands\DataNode
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class PluginCreate extends BaseCommand {


	/**
	 * @param $arguments
	 *
	 * @return int
	 */
	public function run($arguments): int {
		$configurationsPath = SRC_PATH . '/conf/plugins/configurations/';

		$pluginBuilder = new PluginBuilder();
		$pluginBuilder->createConfiguration($this->console);

		$configuration = $pluginBuilder->serializeConfiguration();

		while (true) {
			$filename = $this->console->ask("Enter the filename where you want to save this configuration:");

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

		$this->console->writeLine("The configuration has been saved to " . realpath($configurationsPath . $filename));

		return 0;
	}


}
