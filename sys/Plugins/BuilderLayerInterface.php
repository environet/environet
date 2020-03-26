<?php


namespace Environet\Sys\Plugins;

use Environet\Sys\Commands\Console;

/**
 * Interface BuilderLayerInterface
 * @package Environet\Sys\Plugins
 */
interface BuilderLayerInterface {


	/**
	 * Serialize configuration for the ini file
	 *
	 * @return string
	 */
	public function serializeConfiguration(): string;


    /**
     * Get a human friendly name to identify the layer implementation
     *
     * @return string
     */
    public static function getName(): string;


    /**
     * Get a description of the plugin layer implementation
     *
     * @return string
     */
    public static function getDescription(): string;


	/**
	 * Create an instance during plugin creation
	 *
	 * @param Console $console
	 *
	 * @return mixed
	 */
	public static function create(Console $console);


}
