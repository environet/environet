<?php

namespace Environet\Sys\Upload;

use Environet\Sys\General\HttpClient\Response;
use Environet\Sys\General\Request;
use Exception;
use SimpleXMLElement;

/**
 * Class UploadFileResponse
 *
 * @package Environet\Sys\Upload
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class UploadFileResponse {

	protected Statistics $statistics;

	protected array $errorMessages = [];

	protected array $warningMessages = [];

	protected array $successMessages = [];

	protected string $originalFileName;


	/**
	 * @param string $originalFileName
	 */
	public function __construct(string $originalFileName) {
		$this->originalFileName = $originalFileName;
	}


	/**
	 * @param Response $response
	 * @param Request  $request
	 *
	 * @return UploadFileResponse
	 * @throws Exception
	 */
	public function setFromResponse(Response $response, Request $request): UploadFileResponse {
		if ($response->getStatusCode() === 200) {
			//Log in event logger
			$this->statistics = Statistics::fromXml(new SimpleXMLElement($response->getBody()));
			$this->statistics->setUserId($request->getIdentity() ? (int) $request->getIdentity()->getId() : null);
		} else {
			//Some error returned from upload API
			if (($xml = simplexml_load_string($response->getBody())) !== false &&
				($messages = $xml->xpath('/environet:ErrorResponse/environet:Error/environet:ErrorMessage'))
			) {
				//Valid XML error, parse error messages from error XML
				$this->errorMessages = array_map(function (SimpleXMLElement $element) {
					return (string) $element;
				}, $messages);
				$this->errorMessages = array_filter($this->errorMessages, function ($message) {
					return strpos($message, 'REMOTE_ADDR') === false && strpos($message, 'Username') === false;
				});
			} else {
				//Not a valid XML error, unknown
				$this->errorMessages = ['Unknown error while sending data to upload api endpoint'];
			}
		}

		return $this;
	}


	/**
	 * @param array $errorMessages
	 *
	 * @return UploadFileResponse
	 */
	public function setFromErrors(array $errorMessages): UploadFileResponse {
		$this->errorMessages = $errorMessages;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function hasErrors(): bool {
		return !empty($this->errorMessages);
	}


	/**
	 * @return string
	 */
	public function getOriginalFileName(): string {
		return $this->originalFileName;
	}


	/**
	 * @return Statistics
	 */
	public function getStatistics(): Statistics {
		return $this->statistics;
	}


	/**
	 * @return array
	 */
	public function getErrorMessages(): array {
		return $this->errorMessages;
	}


	/**
	 * @param string $message
	 *
	 * @return $this
	 */
	public function addErrorMessage(string $message): UploadFileResponse {
		$this->errorMessages[] = $message;

		return $this;
	}


	/**
	 * @return array
	 */
	public function getSuccessMessages(): array {
		return $this->successMessages;
	}


	/**
	 * @param string $message
	 *
	 * @return $this
	 */
	public function addSuccessMessage(string $message): UploadFileResponse {
		$this->successMessages[] = $message;

		return $this;
	}


	/**
	 * @return array
	 */
	public function getWarningMessages(): array {
		return $this->warningMessages;
	}


	/**
	 * @param string $message
	 *
	 * @return $this
	 */
	public function addWarningMessage(string $message): UploadFileResponse {
		$this->warningMessages[] = $message;

		return $this;
	}


	/**
	 * @param array $messages
	 *
	 * @return $this
	 */
	public function setWarningMessages(array $messages): UploadFileResponse {
		$this->warningMessages = $messages;

		return $this;
	}


}