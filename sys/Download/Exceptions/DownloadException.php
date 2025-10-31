<?php


namespace Environet\Sys\Download\Exceptions;

use Environet\Sys\General\Exceptions\ApiException;

/**
 * Class DownloadException
 *
 * Common exception for download API errors.
 *
 * @package Environet\Sys\Download\Exceptions
 * @author  SRG Group <dev@srg.hu>
 */
class DownloadException extends ApiException {


	/**
	 * DownloadException constructor.
	 *
	 * Merges error codes and messages unique for this exception with {@see ApiException::$errors}.
	 *
	 * @param int   $code
	 * @param array $errorMessages
	 */
	public function __construct(int $code = 101, array $errorMessages = []) {
		$this->errors += [

			//Input error
			302 => 'Observation point type is missing',
			303 => 'Observation point type is invalid',
			304 => 'Start time filter value is invalid',
			305 => 'End time filter value is invalid',
			306 => 'Invalid output format',
			307 => 'Invalid output format options parameter',

			//Process error
			401 => 'Unauthorized request of monitoring point data!',
			402 => 'Invalid date interval entry, please contact the administrators!'
		];
		parent::__construct($code, $errorMessages);
	}


}
