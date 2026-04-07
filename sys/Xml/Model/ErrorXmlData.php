<?php

namespace Environet\Sys\Xml\Model;

/**
 * Class ErrorXmlData
 *
 * Data model for error response xml
 *
 * @package Environet\Sys\Xml\Model
 * @author  SRG Group <dev@srg.hu>
 */
class ErrorXmlData {

	/**
	 * @var string
	 */
	protected $code;

	/**
	 * @var string
	 */
	protected $message;


	/**
	 * ErrorXmlData constructor.
	 *
	 *
	 * @uses ErrorXmlData::setCode
	 * @uses ErrorXmlData::setMessage
	 */
	public function __construct(?int $code = null, ?string $message = null) {
		if (!is_null($code)) {
			$this->setCode($code);
		}
		if (!is_null($message)) {
			$this->setMessage($message);
		}
	}


	/**
	 * Get error code
	 */
	public function getCode(): string {
		return $this->code;
	}


	/**
	 * Set error code
	 *
	 *
	 */
	public function setCode(string $code): ErrorXmlData {
		$this->code = $code;

		return $this;
	}


	/**
	 * Get error message
	 *
	 */
	public function getMessage(): string {
		return $this->message;
	}


	/**
	 * Set error message
	 *
	 *
	 */
	public function setMessage(string $message): ErrorXmlData {
		$this->message = $message;

		return $this;
	}


}
