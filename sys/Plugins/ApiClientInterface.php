<?php


namespace Environet\Sys\Plugins;

use Environet\Sys\General\HttpClient\Response;
use SimpleXMLElement;

/**
 * Interface ApiClientInterface
 *
 * Interface for the ability to upload XML payloads to distribution nodes.
 *
 * @package Environet\Sys\Plugins
 * @author  SRG Group <dev@srg.hu>
 */
interface ApiClientInterface {


	/**
	 * Upload an XML file to the distribution node.
	 *
	 * @param SimpleXMLElement $payload
	 *
	 * @return Response
	 */
	public function upload(SimpleXMLElement $payload): Response;


}
