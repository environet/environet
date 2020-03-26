<?php
/**
 * Interface ApiException
 *
 * @package   Environet\Sys\General\Exceptions
 * @author    SRG Group <dev@srg.hu>
 * @copyright 2020 SRG Group Kft.
 */

namespace Environet\Sys\General\Exceptions;

use Environet\Sys\Xml\Model\ErrorXmlData;

/**
 * Class ApiException
 *
 * @package   Environet\Sys\General\Exceptions
 * @author    SRG Group <dev@srg.hu>
 * @copyright 2020 SRG Group Kft.
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
	];

	/**
	 * @var array Array of error messages of exception. One exception can represent multiple error messages
	 */
	protected $errorMessages = [];


	/**
	 * UploadException constructor.
	 *
	 * @param int   $code
	 * @param array $errorMessages
	 */
	public function __construct(int $code = 101, array $errorMessages = []) {
		//Add the default error message of code first
		$this->errorMessages[] = $this->errors[$code] ?? $this->errors[101];

		//Optionally add other error messages
		if (!empty($errorMessages)) {
			$this->errorMessages = array_merge($this->errorMessages, $errorMessages);
		}
		parent::__construct(null, $code);
	}


	/**
	 * Get the array of error messages which will be compatible with error response xml
	 *
	 * @return array|ErrorXmlData[]
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