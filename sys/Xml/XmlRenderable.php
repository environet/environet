<?php


namespace Environet\Sys\Xml;

use SimpleXMLElement;

/**
 * Interface XmlRenderable
 *
 * Common interface for classes capable of outputting XML data
 *
 * @package   Environet\Sys\Xml
 * @author    SRG Group <dev@srg.hu>
 * @copyright 2020 SRG Group Kft.
 */
interface XmlRenderable {


	/**
	 * Output XML string
	 *
	 * @param SimpleXMLElement $parent
	 */
	public function render(SimpleXMLElement &$parent);


}
