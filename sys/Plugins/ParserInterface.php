<?php


namespace Environet\Sys\Plugins;

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
	 * @param string $data
	 *
	 * Parse input string data (string is from for example a file)
	 *
	 * @return SimpleXMLElement[]
	 */
	public function parse(string $data): array;


}
