<?php


namespace Environet\Sys\General\Exceptions;


use Exception;

/**
 * Class PKIException
 *
 * Exception in case of PKI errors
 *
 * @package Environet\Sys\General\Exceptions
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class PKIException extends Exception {


	/**
	 * Create the error message based on the openssl error list, and initialize a new exception with this message
	 *
	 * @return static
	 */
	public static function openSSLError() {
		$messages = [];
		while ($msg = openssl_error_string()) {
			$messages[] = $msg;
		}
		return new static(sprintf('Can\'t create signature: '.implode(' | ', $messages)));
	}


}