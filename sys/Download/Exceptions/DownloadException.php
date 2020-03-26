<?php


namespace Environet\Sys\Download\Exceptions;

use Environet\Sys\General\Exceptions\ApiException;

/**
 * Class DownloadException
 *
 * @package   Environet\Sys\Download\Exceptions
 * @author    SRG Group <dev@srg.hu>
 * @copyright 2020 SRG Group Kft.
 */
class DownloadException extends ApiException {


	/**
	 * UploadException constructor.
	 *
	 * @param int   $code
	 * @param array $errorMessages
	 */
	public function __construct(int $code = 101, array $errorMessages = []) {
		$this->errors += [
			//Auth errors
			207 => 'Request token not found',

			//Input error
			301 => 'Signature is invalid',
			302 => 'Observation point type is missing',
			303 => 'Observation point type is invalid',
			304 => 'Start time filter value is invalid',
			305 => 'End time filter value is invalid',

			//Process error
		];
		parent::__construct($code, $errorMessages);
	}


}
