<?php

namespace Environet\Sys\Upload;

use Environet\Sys\General\Exceptions\ApiException;
use Environet\Sys\General\HttpClient\ApiHandler;
use Environet\Sys\General\Response;
use Environet\Sys\Upload\Exceptions\UploadException;
use Environet\Sys\Xml\CreateErrorXml;
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
				// Syntax error
				throw new UploadException(302);
			}

			try {
				// Validate the XML against XSD schema
				(new SchemaValidator($parsedXml, SRC_PATH . '/public/schemas/environet.xsd'))->validate();
			} catch (SchemaInvalidException $e) {
				// XML is invalid
				throw UploadException::schemaErrors($e->getErrorMessages());
			} catch (Exception $e) {
				// Other error during validation
				throw UploadException::serverError();
			}

			try {
				// Input is valid syntactically and semantically valid, process it
				$this->createInputProcessor($parsedXml)->process();
			} catch (InputXmlProcessException $e) {
				// There are some invalid values in XML
				throw new UploadException(401);
			}
		} catch (UploadException $e) {
			return (new Response((new CreateErrorXml())->generateXml($e->getErrorXmlData())->asXML()))
				->setStatusCode(400)
				->setHeaders(['Content-type: application/xml']);
		} catch (Throwable $e) {
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

		if (!$signatureValid) {
			// Signature is not valid
			throw new ApiException(208);
		}
	}
	
	
	/**
	 * Create input processor based on the mpoint type. Type is detected with finding it in the type's database table
	 *
	 * @param SimpleXMLElement $xml Parsed XML
	 *
	 * @return AbstractInputXmlProcessor
	 * @throws UploadException
	 * @see MeteoInputXmlProcessor
	 * @see HydroInputXmlProcessor
	 */
	protected function createInputProcessor(SimpleXMLElement $xml): AbstractInputXmlProcessor {
		if (($hydroProcessor = new HydroInputXmlProcessor($xml))->isValidType()) {
			return $hydroProcessor;
		}
		if (($meteoProcessor = new MeteoInputXmlProcessor($xml))->isValidType()) {
			return $meteoProcessor;
		}
		
		throw new UploadException(402);
	}


	/**
	 * Store raw input XML data.
	 *
	 * @param string $content
	 */
	protected function storeInputData(string $content): void {
		$dir = SRC_PATH . '/data/input_xmls';
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		file_put_contents($dir . '/' . time() . '.xml', $content);
	}


}
