<?php


namespace Environet\Sys\Plugins;

use SimpleXMLElement;

/**
 * Interface XmlGeneratorInterface
 * @package Environet\Sys\Plugins
 */
interface XmlGeneratorInterface {


	/**
	 * Generate xml from an array data which is coming from the parser layer
	 *
	 * @param array $data
	 *
	 * @return SimpleXMLElement
	 */
	public function generateXml(array $data): SimpleXMLElement;


}
