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
	 * @param int|null    $code
	 * @param string|null $message
	 *
	 * @uses \Environet\Sys\Xml\Model\ErrorXmlData::setCode()
	 * @uses \Environet\Sys\Xml\Model\ErrorXmlData::setMessage()
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
	 *
	 * @param string $message
	 *
	 * @return ErrorXmlData
	 */
	public function setMessage(string $message): ErrorXmlData {
		$this->message = $message;

		return $this;
	}


}
