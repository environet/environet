<?php

namespace Environet\Sys\General\Enums;

/**
 * Class MessageCodes
 *
 * Enum class for message codes
 */
class MessageCodes {

	const UPLOAD_WARNING_POINT_NOT_FOUND = 1;


	/**
	 * Get a human readable message for a given code. Optionally replace placeholders in the message with the given parameters.
	 *
	 * @param int        $code
	 * @param array|null $messageParams
	 *
	 * @return string|null
	 */
	public static function getMessage(int $code, array $messageParams = null): ?string {
		switch ($code) {
			case self::UPLOAD_WARNING_POINT_NOT_FOUND:
				$message = 'Monitoring point not found with the given identifier, nothing was uploaded';
				break;
			default:
				return null;
		}

		if ($messageParams === null) {
			//No parameters to replace, return the message as is
			return $message;
		}

		//Replace placeholders in the message
		return sprintf($message, ...$messageParams);
	}


}
