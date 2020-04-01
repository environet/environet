<?php


namespace Environet\Sys\Plugins;

use Environet\Sys\Commands\Console;

/**
 * Class PluginLayer
 *
 * @package Environet\Sys\Plugins
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class PluginLayer {

	/**
	 * @var string Name of layer
	 */
	private $name;

	/**
	 * @var BuilderLayerInterface[] Layer alternatives
	 */
	private $alternatives = [];


	/**
	 * PluginLayer constructor.
	 *
	 * @param $name
	 * @param $alternatives
	 */
	public function __construct($name, $alternatives) {
		$this->name = $name;
		$this->alternatives = $alternatives;
	}


	/**
	 * @return mixed
	 */
	public function getName() {
		return $this->name;
	}


	/**
	 * Create configuration during install command
	 *
	 * @param Console $console
	 *
	 * @return mixed
	 */
	public function createConfiguration(Console $console) {
		$class = $this->chooseAlternative($console);
		return $class::create($console);
	}


	/**
	 * Ask for alternative if the current layer has any.
	 *
	 * @param Console $console
	 *
	 * @return mixed
	 */
	private function chooseAlternative(Console $console) {
		if (count($this->alternatives) > 1) {
			$console->writeLine("Choose a $this->name implementation:");
			$console->writeLine('');
			foreach ($this->alternatives as $i => $alternative) {
				$console->writeLine($i + 1 . ": " . $alternative::getName());
			}
            $console->writeLine('');
			$choice = $console->askOption();

			return ($this->alternatives[(int) $choice - 1]);
		}

		return $this->alternatives[0];
	}


}
