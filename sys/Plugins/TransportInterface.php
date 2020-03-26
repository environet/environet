<?php


namespace Environet\Sys\Plugins;

/**
 * Interface TransportInterface
 * @package Environet\Sys\Plugins
 */
interface TransportInterface {


	/**
	 * Get string from a resource
	 * @return string
	 */
	public function get(): string;


}
