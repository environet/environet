<?php

namespace Environet\Sys\Plugins;

use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\Parsers\JsonParser;
use Environet\Sys\Plugins\Transports\HttpTransport;
use Environet\Sys\Plugins\Transports\LocalFileTransport;
use Environet\Sys\Plugins\XmlGenerators\MPointPropertyXmlInputGenerator;

/**
 * Class PluginBuilder
 *
 * @package Environet\Sys\Plugins
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class PluginBuilder {

	/** @var Plugin */
	private $plugin;

	/** @var PluginLayer[]  */
	private $layers = [];


	/**
	 * PluginBuilder constructor.
	 */
	public function __construct() {
		$this->layers = [
			new PluginLayer('transport', [ HttpTransport::class, LocalFileTransport::class]),
			new PluginLayer('parser', [JsonParser::class]),
			new PluginLayer('xmlGenerator', [MPointPropertyXmlInputGenerator::class]),
			new PluginLayer('apiClient', [ApiClient::class])
		];
	}


	/**
	 * @param Console $console
	 *
	 * @return Plugin
	 */
	public function createConfiguration(Console $console): Plugin {
		$this->plugin = new Plugin();

		foreach ($this->layers as $layer) {
			$this->plugin->{$layer->getName()} = $layer->createConfiguration($console);
		}

		return $this->plugin;
	}


	/**
	 * @param $config
	 *
	 * @return Plugin
	 */
	public function loadFromConfiguration($config): Plugin {
		$this->plugin = new Plugin();
		foreach ($this->layers as $layer) {
			$this->plugin->{$layer->getName()} = new $config[$layer->getName()]['className']($config[$layer->getName()]);
		}

		return $this->plugin;
	}


	/**
	 * @return string
	 */
	public function serializeConfiguration(): string {
		$result = '';

		foreach ($this->layers as $layer) {
			$result .= "[" . $layer->getName() . "]\n";
			$result .= "className = " . get_class($this->plugin->{$layer->getName()}) . "\n";
			$result .= $this->plugin->{$layer->getName()}->serializeConfiguration();
			$result .= "\n";
		}

		return $result;
	}


}
