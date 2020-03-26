<?php


namespace Environet\Sys\Plugins;

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
	 * @return array
	 */
	public function parse(string $data): array;


}
