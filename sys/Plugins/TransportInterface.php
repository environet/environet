<?php


namespace Environet\Sys\Plugins;

use Environet\Sys\Commands\Console;

/**
 * Interface TransportInterface
 *
 * Interface for classes able the fetch a resource by some means of transport.
 *
 * @package Environet\Sys\Plugins
 * @author  SRG Group <dev@srg.hu>
 */
interface TransportInterface {


	/**
	 * Get a list of resources
	 *
	 * @param Console $console
	 * @param string  $configFile
	 *
	 * @return Resource[]
	 */
	public function get(Console $console, string $configFile): array;


}
