<?php

namespace Environet\Sys\Xml\Exceptions;

use Exception;

/**
 * Class SchemaInvalidException
 *
 * XSD validation exception
 *
 * @package Environet\Sys\Xml\Exceptions
 * @author  SRG Group <dev@srg.hu>
 */
class SchemaInvalidException extends Exception {

	/**
	 * @var array
	 */
	private $errorMessages = [];


	/**
	 * SchemaInvalidException constructor.
	 *
	 * Create error messages based on libXmlErrors.
	 *
	 * @param array $libXmlErrors This array should be the output of libxml_get_errors
	 */
	public function __construct(array $libXmlErrors) {
		foreach ($libXmlErrors as $error) {
			// Message prefix based on error level
			switch ($error->level) {
				case LIBXML_ERR_WARNING:
					$messagePrefix = 'Warning';
					break;
				case LIBXML_ERR_FATAL:
					$messagePrefix = 'Fatal error';
					break;
				case LIBXML_ERR_ERROR:
				default:
					$messagePrefix = 'Error';
					break;
			}

			// Build message and append to errorMessages
			$message = trim($error->message);
			$line = trim($error->line);
			$this->errorMessages[] = "$messagePrefix $error->code: $message on line $line";
		}

		// Create a simple one-line message from error messages - just for the compatibility
		$message = implode(' | ', $this->errorMessages);
		parent::__construct($message);
	}


	/**
	 * Get array of validation error messages
	 * @return array
	 */
	public function getErrorMessages(): array {
		return $this->errorMessages;
	}


}
