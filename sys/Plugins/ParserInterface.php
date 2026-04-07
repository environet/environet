<?php


namespace Environet\Sys\Plugins;

use Environet\Sys\Plugins\Resource;
use SimpleXMLElement;

/**
 * Interface ParserInterface
 *
 * Interface for input parser implementations.
 *
 * @package Environet\Sys\Plugins
 * @author  SRG Group <dev@srg.hu>
 */
interface ParserInterface {


	/**
	 * @return SimpleXMLElement[]
	 */
	public function parse(Resource $resource): array;


}
