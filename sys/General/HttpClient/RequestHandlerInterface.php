<?php


namespace Environet\Sys\General\HttpClient;

/**
 * Interface RequestHandlerInterface
 *
 * Provides instructions for handling requests
 *
 * @package Environet\Sys\General\HttpClient
 * @author  SRG Group <dev@srg.hu>
 */
interface RequestHandlerInterface {


	/**
	 * Handle the incoming HTTP request.
	 *
	 * @return mixed
	 */
	public function handleRequest();


}
