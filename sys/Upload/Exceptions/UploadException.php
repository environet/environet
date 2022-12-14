<?php


namespace Environet\Sys\Upload\Exceptions;

use Environet\Sys\General\Exceptions\ApiException;

/**
 * Class UploadException
 *
 * Common exception for upload API errors.
 *
 * @package Environet\Sys\Upload\Exceptions
 * @author  SRG Group <dev@srg.hu>
 */
class UploadException extends ApiException {


	/**
	 * UploadException constructor.
	 *
	 * Merges error codes and messages unique for this exception with {@see ApiException::$errors}.
	 *
	 * @param int   $code
	 * @param array $errorMessages
	 */
	public function __construct(int $code = 101, array $errorMessages = []) {
		$this->errors += [
			//Input error
			302 => 'Xml syntax is invalid',
			303 => 'Xml is invalid against schema',

			//Process error
			401 => 'Error during processing data',
			402 => 'Monitoring point not found with the given identifier',
			403 => 'Property for the selected monitoring point not found, or not allowed',
			404 => 'Could not initialize time series for monitoring point and property',
			405 => 'Monitoring point is inactive'
		];
		parent::__construct($code, $errorMessages);
	}


	/**
	 * Shortcut function for schema errors. Validation messages added as custom error messages
	 *
	 * @param array $errors
	 *
	 * @return self
	 */
	public static function schemaErrors(array $errors): self {
		return new static(303, $errors);
	}


}
