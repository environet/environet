<?php


namespace Environet\Sys\Plugins;

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
	 * @param array $variables associative array with array names and contents to resolve 
	 * @return Resource[]
	 */
	public function get(array $variables): array;


}
