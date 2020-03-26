<?php


namespace Environet\Sys\Plugins;

use Environet\Sys\General\HttpClient\Response;
use SimpleXMLElement;

/**
 * Interface ApiClientInterface
 * @package Environet\Sys\Plugins
 */
interface ApiClientInterface {


	/**
	 * @param SimpleXMLElement $payload
	 *
	 * @return Response
	 */
	public function upload(SimpleXMLElement $payload): Response;


}
