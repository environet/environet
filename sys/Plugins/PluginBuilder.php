<?php

namespace Environet\Sys\Plugins;

use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\Parsers\CsvParser;
use Environet\Sys\Plugins\Parsers\JsonParser;
use Environet\Sys\Plugins\Transports\HttpTransport;
use Environet\Sys\Plugins\Transports\HttpTransportExtended;
use Environet\Sys\Plugins\Transports\LocalDirectoryTransport;
use Environet\Sys\Plugins\Transports\LocalFileTransport;

/**
 * Class PluginBuilder
 *
 * Builds a plugins and outputs it's generated configuration.
 *
 * @package Environet\Sys\Plugins
 * @author  SRG Group <dev@srg.hu>
 */
class PluginBuilder {

	/** @var Plugin */
	private $plugin;

	/** @var PluginLayer[] */
	private $layers = [];


	/**
	 * PluginBuilder constructor.
	 * Sets the available layers (transport, parser, client)
	 */
	public function __construct() {
		$this->layers = [
			new PluginLayer(
				'transport',
				[HttpTransport::class, HttpTransportExtended::class, LocalFileTransport::class, LocalDirectoryTransport::class],
				'Choose a transport layer implementation. This determines the mechanism by which the plugin will access the data.'
			),
			new PluginLayer(
				'parser',
				[JsonParser::class, CsvParser::class],
				'Choose a parser layer implementation. It will be used to transform the data acquired through the transport into an API compatible XML format.'
			),
			new PluginLayer(
				'apiClient',
				[ApiClient::class],
				''
			)
		];
	}


	/**
	 * Create a new plugin and set it's layers' configuration.
	 *
	 * @param Console $console
	 *
	 * @return Plugin
	 * @uses \Environet\Sys\Plugins\PluginLayer::createConfiguration()
	 */
	public function createConfiguration(Console $console): Plugin {
		$this->plugin = new Plugin();

		foreach ($this->layers as $layer) {
			$console->writeLine($layer->getHelp(), Console::COLOR_YELLOW);
			$console->writeLine('');
			$this->plugin->{$layer->getName()} = $layer->createConfiguration($console);
		}

		return $this->plugin;
	}


	/**
	 * Create a new plugin from an existing configuration.
	 *
	 * @param $config
	 *
	 * @return Plugin
	 * @uses \Environet\Sys\Plugins\PluginLayer::getName()
	 */
	public function loadFromConfiguration($config): Plugin {
		$this->plugin = new Plugin();

		foreach ($this->layers as $layer) {
			$this->plugin->{$layer->getName()} = new $config[$layer->getName()]['className']($config[$layer->getName()]);
		}

		return $this->plugin;
	}


	/**
	 * Serializes the created plugin's configuration.
	 * For each layer used, the following gets stored in a string, in separate lines:
	 * - The layer's name
	 * - The class of the layer implementation
	 * - The serialized configuration object of the layer
	 *
	 * @return string
	 * @uses \Environet\Sys\Plugins\BuilderLayerInterface::getName()
	 * @uses \Environet\Sys\Plugins\BuilderLayerInterface::serializeConfiguration()
	 */
	public function serializeConfiguration(): string {
		$result = '';

		foreach ($this->layers as $layer) {
			$result .= sprintf(
				"[%s]\nclassName = %s\n%s\n",
				$layer->getName(),
				get_class($this->plugin->{$layer->getName()}),
				$this->plugin->{$layer->getName()}->serializeConfiguration()
			);
		}

		return $result;
	}


}
