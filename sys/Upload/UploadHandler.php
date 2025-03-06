<?php

namespace Environet\Sys\Upload;

use DateTime;
use DateTimeZone;
use Environet\Sys\Config;
use Environet\Sys\General\Db\HydroObservedPropertyQueries;
use Environet\Sys\General\Db\MeteoObservedPropertyQueries;
use Environet\Sys\General\Enums\MessageCodes;
use Environet\Sys\General\Exceptions\ApiException;
use Environet\Sys\General\HttpClient\ApiHandler;
use Environet\Sys\General\Model\UploadOptions;
use Environet\Sys\General\Response;
use Environet\Sys\General\SysIdentity;
use Environet\Sys\Upload\Exceptions\UploadException;
use Environet\Sys\Xml\CreateErrorXml;
use Environet\Sys\Xml\CreateUploadStatisticsXml;
use Environet\Sys\Xml\Exceptions\InputXmlProcessException;
use Environet\Sys\Xml\Exceptions\SchemaInvalidException;
use Environet\Sys\Xml\InputProcessor\AbstractInputXmlProcessor;
use Environet\Sys\Xml\InputProcessor\HydroInputXmlProcessor;
use Environet\Sys\Xml\InputProcessor\MeteoInputXmlProcessor;
use Environet\Sys\Xml\Model\ErrorXmlData;
use Environet\Sys\Xml\SchemaValidator;
use Exception;
use SimpleXMLElement;
use Throwable;

/**
 * Class UploadHandler
 *
 * The core entry-point for upload API requests
 *
 * @package Environet\Sys\Upload
 * @author  SRG Group <dev@srg.hu>
 */
class UploadHandler extends ApiHandler {

	/** @inheritDoc */
	protected const HANDLER_PERMISSION = 'api.upload';


	/**
	 * Handle the upload request.
	 *
	 * Validates xml input, and stores data into database.
	 * If an error occurs, an ErrorResponse XML will be generated ({@see CreateErrorXml}).
	 *
	 * @return Response|mixed
	 * @uses \Environet\Sys\General\HttpClient\ApiHandler::getIdentity()
	 * @uses \Environet\Sys\Upload\UploadHandler::authorizeRequest()
	 * @uses \Environet\Sys\Upload\UploadHandler::getAuthHeaderParts()
	 * @uses \Environet\Sys\Upload\UploadHandler::storeInputData()
	 * @uses \Environet\Sys\Upload\UploadHandler::createInputProcessor()
	 */
	public function handleRequest() {
		try {
			$this->authorizeRequest();

			$content = file_get_contents('php://input');
			$this->storeInputData($content);

			try {
				// Parse the XML with simpleXML
				$parsedXml = new SimpleXMLElement($content);
			} catch (Exception $exception) {
				exception_logger($exception);

				// Syntax error
				throw new UploadException(302, [], $this->identity->getData());
			}

			try {
				// Validate the XML against XSD schema
				(new SchemaValidator($parsedXml, SRC_PATH . '/public/schemas/environet.xsd'))->validate();
			} catch (SchemaInvalidException $e) {
				// XML is invalid
				throw UploadException::schemaErrors($e->getErrorMessages(), $this->identity->getData());
			} catch (Exception $e) {
				exception_logger($e);

				// Other error during validation
				throw UploadException::serverError();
			}

			$isStatisticsRequest = ($this->request->getPathParts()[1] ?? null) === 'statistics';
			define('UPLOAD_DRY_RUN', $isStatisticsRequest);

			//Define a common 'now' date, which will be used in the upload process everywhere
			$nowDate = new DateTime('now', (new DateTimeZone('UTC')));
			$options = (new UploadOptions())->initFromXml($parsedXml);

			try {
				// Input is valid syntactically and semantically valid, process it
				$processor = $this->createInputProcessor($parsedXml, $options);
				$processor->process($this->getIdentity(), $nowDate, $options);

				return (new Response($processor->getStatistics()->toXml()->asXML()))
					->setStatusCode(200)
					->setHeaders(['Content-type: application/xml']);
			} catch (UploadException $e) {
				if ($e->getCode() === 402 && $options->isIgnoreUndefinedPoints() && isset($processor)) {
					//If the upload option is set to ignore undefined points, and the monitoring point is not found, return the statistics with a warning message
					$processor->getStatistics()->addMessage('warning', null, MessageCodes::UPLOAD_WARNING_POINT_NOT_FOUND);

					return (new Response($processor->getStatistics()->toXml()->asXML()))
						->setStatusCode(200)
						->setHeaders(['Content-type: application/xml']);
				}
				throw $e;
			} catch (InputXmlProcessException $e) {
				// There are some invalid values in XML
				throw new UploadException(401, [], $this->identity->getData());
			}
		} catch (UploadException $e) {
			exception_logger($e);

			return (new Response((new CreateErrorXml())->generateXml($e->getErrorXmlData())->asXML()))
				->setStatusCode(400)
				->setHeaders(['Content-type: application/xml']);
		} catch (Throwable $e) {
			exception_logger($e);

			return (new Response((new CreateErrorXml())->generateXml([new ErrorXmlData(500, $e->getMessage())])->asXML()))
				->setStatusCode(500)
				->setHeaders(['Content-type: application/xml']);
		}
	}


	/**
	 * Validate the request signature in the auth header, against the request body
	 *
	 * @throws ApiException
	 */
	protected function validateSignature() {
		// Get the XML content, and verify the signature with user's public key
		$content = file_get_contents('php://input');
		$hash = md5($content);
		$signature = base64_decode($this->getAuthHeaderParts()['signature'] ?? '');
		$publicKey = $this->identity->getPublicKey();
		$signatureValid = openssl_verify($hash, $signature, $publicKey, OPENSSL_ALGO_SHA256);

		// If the signature is not valid, and the request is called from the php container, check the signature with the sys public key
		if (!$signatureValid && gethostbyname('dist_php') === $_SERVER['REMOTE_ADDR']) {
			$signatureValid = @openssl_verify($hash, $signature, file_get_contents(SysIdentity::getSysPublicKeyFile()), OPENSSL_ALGO_SHA256);
		}

		if (!$signatureValid) {
			// Signature is not valid
			throw new ApiException(208, [], $this->identity->getData());
		}
	}


	/**
	 * Create input processor based on the mpoint type. Type is detected with finding it in the type's database table
	 *
	 * @param SimpleXMLElement $xml     Parsed XML
	 * @param UploadOptions    $options
	 *
	 * @return AbstractInputXmlProcessor
	 * @throws ApiException
	 * @throws UploadException
	 * @see MeteoInputXmlProcessor
	 * @see HydroInputXmlProcessor
	 */
	protected function createInputProcessor(SimpleXMLElement $xml, UploadOptions $options): AbstractInputXmlProcessor {
		$monitoringPointId = (string) $xml->xpath('/environet:UploadData/environet:MonitoringPointId[1]')[0] ?? null;
		$messages = [
			'NCD: ' . $monitoringPointId,
		];

		//Get all properties from the XML
		$inputProperties = $xml->xpath('/environet:UploadData/environet:Property/environet:PropertyId') ?? [];
		$inputProperties = array_map(fn(SimpleXMLElement $property) => strtolower((string) $property), $inputProperties);

		//Collect all observed properties from the database
		$properties = [];
		foreach (HydroObservedPropertyQueries::getOptionList('symbol') as $symbol) {
			$properties[strtolower($symbol)] = 'hydro';
		}
		foreach (MeteoObservedPropertyQueries::getOptionList('symbol') as $symbol) {
			$properties[strtolower($symbol)] = 'meteo';
		}

		//Detect type based on the input properties
		$detectedType = null;
		foreach ($inputProperties as $inputProperty) {
			if (!array_key_exists($inputProperty, $properties)) {
				//Not a valid property, will be validated later
				continue;
			}

			//Property is valid, get the type of property
			$type = $properties[$inputProperty];

			//If xml already has a detected type, and the current property's type is different, throw an error
			if (!is_null($detectedType) && $type !== $detectedType) {
				throw new UploadException(406, $messages, $this->identity->getData());
			}

			//Set the detected type
			$detectedType = $type;
		}

		if (!$detectedType) {
			//No dected type, empty or invalid properites
			throw new UploadException(407, $messages, $this->identity->getData());
		}

		if ((($hydroProcessor = new HydroInputXmlProcessor($xml))->isValidType($this->getIdentity()) || $options->isIgnoreUndefinedPoints()) && $detectedType === 'hydro') {
			return $hydroProcessor;
		}
		if ((($meteoProcessor = new MeteoInputXmlProcessor($xml))->isValidType($this->getIdentity()) || $options->isIgnoreUndefinedPoints()) && $detectedType === 'meteo') {
			return $meteoProcessor;
		}

		throw new UploadException(402, $messages);
	}


	/**
	 * Store raw input XML data.
	 *
	 * @param string $content
	 */
	protected function storeInputData(string $content): void {
		$config = Config::getInstance();
		$storeInputXmls = $config->getStoreInputXmls();
		if ($storeInputXmls) {
			$dir = SRC_PATH . '/data/input_xmls';
			if (!is_dir($dir)) {
				mkdir($dir, 0755, true);
			}
			file_put_contents($dir . '/' . time() . '.xml', $content);
		}
	}


}
