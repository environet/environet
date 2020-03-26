<?php

namespace Environet\Sys\Xml\Model;

/**
 * Class ErrorXmlData
 *
 * Data model for error response xml
 *
 * @package Sys\Xml
 * @author  Ádám Bálint <adam.balint@srg.hu>
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
	 * InputXmlData constructor.
	 *
	 * @param int|null    $code
	 * @param string|null $message
	 */
	public function __construct(int $code = null, string $message = null) {
		if (!is_null($code)) {
			$this->setCode($code);
		}
		if (!is_null($message)) {
			$this->setMessage($message);
		}
	}


	/**
	 * Get error code
	 * @return string
	 */
	public function getCode(): string {
		return $this->code;
	}


	/**
	 * Set error code
	 *
	 * @param string $code
	 *
	 * @return ErrorXmlData
	 */
	public function setCode(string $code): ErrorXmlData {
		$this->code = $code;

		return $this;
	}


	/**
	 * Get error message
	 *
	 * @return string
	 */
	public function getMessage(): string {
		return $this->message;
	}


	/**
	 * Set error message
	 * w
	 * @param string $message
	 *
	 * @return ErrorXmlData
	 */
	public function setMessage(string $message): ErrorXmlData {
		$this->message = $message;

		return $this;
	}


}
