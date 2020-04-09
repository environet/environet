<?php


namespace Environet\Sys\Plugins;

use Environet\Sys\Commands\Console;

/**
 * Class PluginLayer
 *
 * Wrapper class for a transport, parser or client layer to be created in a plugin.
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
	 * @var string Help text to provide context for the choices, and to explain to the user what the layer is for.
	 */
	private $helpText = [];

	/**
	 * @var BuilderLayerInterface[] Layer alternatives
	 */
	private $alternatives = [];


	/**
	 * PluginLayer constructor.
	 * Sets the name and the alternatives array.
	 *
	 * @param string $name
	 * @param string[] $alternatives
	 * @param string $helpText
	 */
	public function __construct(string $name, array $alternatives, string $helpText) {
		$this->name = $name;
		$this->alternatives = $alternatives;
		$this->helpText = $helpText;
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
	 * Get the layer's name.
	 *
	 * @return mixed
	 */
	public function getHelp() {
		return $this->helpText;
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
			foreach ($this->alternatives as $i => $alternative) {
				$console->writeLine($i + 1 . ": " . $alternative::getName());
				$console->writeLine($alternative::getHelp());
				$console->writeLine('');
			}
			$console->writeLine('');
			$choice = $console->askOption("Enter a number corresponding to the $this->name implementation of your choice:");

			return ($this->alternatives[(int) $choice - 1]);
		}

		return $this->alternatives[0];
	}


}
