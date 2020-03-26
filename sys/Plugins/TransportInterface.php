<?php


namespace Environet\Sys\Plugins;

/**
 * Interface TransportInterface
 * @package Environet\Sys\Plugins
 */
interface TransportInterface {


	/**
	 * Get a list of resources
	 * @return string[]
	 */
	public function get(): array;


}
