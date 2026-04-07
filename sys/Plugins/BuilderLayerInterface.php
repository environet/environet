<?php


namespace Environet\Sys\Plugins;

use Environet\Sys\Commands\Console;

/**
 * Interface BuilderLayerInterface
 *
 * Interface for transport layer implementations.
 *
 * @package Environet\Sys\Plugins
 * @author  SRG Group <dev@srg.hu>
 */
interface BuilderLayerInterface {


	/**
	 * Serialize configuration for the ini file
	 *
	 */
	public function serializeConfiguration(): string;


	/**
	 * Get a human friendly name to identify the layer implementation
	 *
	 */
	public static function getName(): string;


	/**
	 * Get a description of the layer implementation
	 *
	 */
	public static function getHelp(): string;


	/**
	 * Create an instance during plugin creation
	 */
	public static function create(Console $console, PluginBuilder $builder);


	public function getConfigArray(): array;


}
