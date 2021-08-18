<?php
/**
 * Interface ApiException
 *
 * @package   Environet\Sys\General\Exceptions
 * @author    SRG Group <dev@srg.hu>
 */

namespace Environet\Sys\General\Exceptions;

use Environet\Sys\Xml\Model\ErrorXmlData;

/**
 * Class ApiException
 *
 * Common exception for api related issues
 *
 * @package Environet\Sys\General\Exceptions
 * @author  SRG Group <dev@srg.hu>
 */
class ApiException extends \Exception {

	/**
	 * @var array
	 */
	protected $errors = [
		//General errors
		101 => 'Unknown error',
		102 => 'Server error',

		//Auth errors
		201 => 'Authorization header is missing',
		202 => 'Username is empty',
		203 => 'User not found with username',
		204 => 'Invalid Authorization header',
		205 => 'Action not permitted',
		206 => 'Public key for user not found',
		207 => 'Request token not found',
		208 => 'Request signature is not valid'
	];

	/**
	 * @var array Array of error messages of exception. One exception can represent multiple error messages
	 */
	protected $errorMessages = [];


	/**
	 * UploadException constructor.
	 * Populates {@see ApiException::$errorMessages} with the message that belongs to the current error code and additional error messages (if there are any).
	 *
	 * @param int   $code          The error code
	 * @param array $errorMessages Optional additional error messages
	 */
	public function __construct(int $code = 101, array $errorMessages = []) {
		// Add the default error message of code first
		$this->errorMessages[] = $this->errors[$code] ?? $this->errors[101];

		// Optionally add other error messages
		if (!empty($errorMessages)) {
			$this->errorMessages = array_merge($this->errorMessages, $errorMessages);
		}
		array_push($this->errorMessages, 'REMOTE_ADDR: ' . substr($_SERVER['REMOTE_ADDR'],0,32) . ", HTTP_X_FORWARDED_FOR: " . substr($_SERVER['HTTP_X_FORWARDED_FOR'],0,128));
		parent::__construct(join(', ', $this->errorMessages), $code);
	}


	/**
	 * Get the array of error messages which will be compatible with error response xml
	 *
	 * @return array|ErrorXmlData[]
	 * @see ErrorXmlData
	 */
	public function getErrorXmlData(): array {
		$xmlErrors = [];
		foreach ($this->errorMessages as $errorMessage) {
			$xmlErrors[] = new ErrorXmlData($this->getCode(), $errorMessage);
		}

		return $xmlErrors;
	}


	/**
	 * Shortcut function for server errors
	 * @return self
	 */
	public static function serverError(): self {
		return new static(102);
	}


	/**
	 * Shortcut function for unknown errors
	 * @return self
	 */
	public static function unknownError(): self {
		return new static(101);
	}
}