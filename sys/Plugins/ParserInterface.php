<?php


namespace Environet\Sys\Plugins;

use SimpleXMLElement;

/**
 * Interface ParserInterface
 * @package Environet\Sys\Plugins
 */
interface ParserInterface {


	/**
	 * @param string $data
	 *
	 * Parse string date (string is from for example a file)
	 *
	 * @return SimpleXMLElement[]
	 */
	public function parse(string $data): array;


}
