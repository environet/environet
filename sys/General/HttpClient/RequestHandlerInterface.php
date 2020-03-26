<?php


namespace Environet\Sys\General\HttpClient;

use Environet\Sys\General\Identity;

/**
 * Interface RequestHandlerInterface
 *
 * Provides instructions for handling requests
 *
 * @package   Environet\Sys\General\HttpClient
 * @author    SRG Group <dev@srg.hu>
 * @copyright 2020 SRG Group Kft.
 */
interface RequestHandlerInterface {


	/**
	 * @return mixed
	 */
	public function handleRequest();


}
