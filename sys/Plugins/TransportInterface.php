<?php


namespace Environet\Sys\Plugins;

/**
 * Interface TransportInterface
 * @package Environet\Sys\Plugins
 */
interface TransportInterface {


	/**
	 * Get a list of resources
	 * @return Resource[]
	 */
	public function get(): array;


}
