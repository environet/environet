<?php


namespace Environet\Sys\Plugins;

use Environet\Sys\Commands\Console;

/**
 * Class PluginLayer
 *
 * @package Environet\Sys\Plugins
 * @author  SRG Group <dev@srg.hu>
 */
class PluginLayer {

	/**
	 * @var string Name of the layer
	 */
	private $name;

	/**
	 * @var BuilderLayerInterface[] Layer alternatives
	 */
	private $alternatives = [];


	/**
	 * PluginLayer constructor.
	 * Sets the name and the alternatives array.
	 *
	 * @param $name
	 * @param $alternatives
	 */
	public function __construct($name, $alternatives) {
		$this->name = $name;
		$this->alternatives = $alternatives;
	}


	/**
	 * Get the layer's name.
	 *
	 * @return mixed
	 */
	public function getName() {
		return $this->name;
	}


	/**
	 * Create configuration during install command.
	 *
	 * @param Console $console
	 *
	 * @return mixed
	 * @uses \Environet\Sys\Plugins\PluginLayer::chooseAlternative()
	 * @uses \Environet\Sys\Plugins\BuilderLayerInterface::create()
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
