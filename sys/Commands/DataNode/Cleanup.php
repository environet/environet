<?php


namespace Environet\Sys\Commands\DataNode;

use Environet\Sys\Commands\BaseCommand;
use Environet\Sys\Plugins\PluginBuilder;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Class Cleanup
 *
 * Cleanup data node
 *
 * @package Environet\Sys\Commands\DataNode
 * @author  SRG Group <dev@srg.hu>
 */
class Cleanup extends BaseCommand {

	/**
	 * Threshold for plugin_input_files in seconds
	 *
	 * @var float|int
	 */
	protected $pluginInputFilesTh = 7 * 24 * 60 * 60; // 1 week


	/**
	 * Run create plugin command.
	 *
	 * Delete unnecessary, old files from data node
	 *
	 *
	 * @param array $arguments
	 * @param array $options
	 *
	 * @return int
	 * @uses \Environet\Sys\Plugins\PluginBuilder::createConfiguration()
	 * @uses \Environet\Sys\Plugins\PluginBuilder::serializeConfiguration()
	 */
	public function run($arguments, $options): int {
		$this->console->writeLine('Start cleanup...');
		$pluginInputFiles = SRC_PATH . '/data/plugin_input_files';

		if (!is_dir($pluginInputFiles)) {
			$this->console->writeLine('plugin_input_files directory not found');
		} else {
			$deletedNumber = 0;
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginInputFiles), RecursiveIteratorIterator::LEAVES_ONLY);
			/** @var SplFileInfo $file */
			$threshold = time() - $this->pluginInputFilesTh;
			foreach ($iterator as $file) {
				if ($file->isDir()) {
					continue;
				}
				if ($file->getMTime() <= $threshold) {
					$deletedNumber ++;
					unlink($file->getRealPath());
				}
			}
			$this->console->writeLine(sprintf("%d files have been deleted", $deletedNumber));
		}

		$this->console->writeLine("Cleanup finished");

		return 0;
	}


}
