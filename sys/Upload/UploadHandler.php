<?php

namespace Environet\Sys\Upload;

use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\PermissionException;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\HttpClient\ApiHandler;
use Environet\Sys\General\Identity;
use Environet\Sys\General\Response;
use Environet\Sys\Upload\Exceptions\UploadException;
use Environet\Sys\Xml\CreateErrorXml;
use Environet\Sys\Xml\Exceptions\InputXmlProcessException;
use Environet\Sys\Xml\Exceptions\SchemaInvalidException;
use Environet\Sys\Xml\InputProcessor\AbstractInputXmlProcessor;
use Environet\Sys\Xml\InputProcessor\HydroInputXmlProcessor;
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
 * @package Environet\Sys\Admin
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class UploadHandler extends ApiHandler {

	/** @inheritDoc */
	protected const HANDLER_PERMISSION = 'api.upload';

	/**
	 * @var null|array The parsed authorization header
	 */
	protected $authHeaderParts = null;

	/**
	 * @var null|Identity The current uploader's identity
	 */
	protected $identity = null;


	/**
	 * Prepare and parse the Authorization header
	 *
	 * @return array
	 * @throws UploadException
	 */
	protected function getAuthHeaderParts(): array {
		if ($this->authHeaderParts === null) {
			// If the header isn't set, the request is invalid
			if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
				throw new UploadException(201);
			}

			// Split the auth header to parts. For format see \Environet\Sys\General\PKI::authHeaderWithSignature
			$this->authHeaderParts = $this->parseAuthHeader($_SERVER['HTTP_AUTHORIZATION']);

			// If the parts aren't present, the request is invalid
			if ($this->authHeaderParts === null) {
				throw new UploadException(204);
			}
		}

		return $this->authHeaderParts;
	}


	/**
	 * @inheritDoc
	 *
	 * @return void
	 * @throws PermissionException
	 * @throws QueryException
	 * @throws UploadException
	 */
	protected function authorizeRequest(): void {
		if (!in_array(self::HANDLER_PERMISSION, $this->getIdentity()->getPermissions())) {
			throw new UploadException(205);
		}
	}


	/**
	 * Handle the upload request.
	 * It checks the auth state (username is presented in header, and an existing user),
	 * verifies the signature,
	 * verifies the xml with XSD schema,
	 * and processes the input
	 *
	 * On error an ErrorResponse XML will be generated
	 */
	public function handleRequest() {
		try {
			// Get the identity based on auth header
			$identity = $this->getIdentity();

			//Only users with public keys are allowed
			if (!$identity->getPublicKey()) {
				throw new UploadException(206);
			}

			$this->authorizeRequest();

			//Get the XML content, and verify the signature with user's public key
			$content = file_get_contents('php://input');
			$hash = md5($content);
			$signature = base64_decode($this->getAuthHeaderParts()['signature'] ?? '');
			$publicKey = $identity->getPublicKey();
			$signatureValid = openssl_verify($hash, $signature, $publicKey, OPENSSL_ALGO_SHA256);

			if (!$signatureValid) {
				//Signature is not valid
				throw new UploadException(301);
			}

			try {
				//Parse the XML with simpleXML
				$parsedXml = new SimpleXMLElement($content);
			} catch (Exception $exception) {
				//Syntax error
				throw new UploadException(302);
			}

			try {
				//Validate the XML against XSD schama
				(new SchemaValidator($parsedXml, SRC_PATH . '/public/schemas/environet.xsd'))->validate();
			} catch (SchemaInvalidException $e) {
				//XML is invalid
				throw UploadException::schemaErrors($e->getErrorMessages());
			} catch (Exception $e) {
				//Other error during validation
				throw UploadException::serverError();
			}

			try {
				//Input is valid syntactically and semantically valid, process it
				$this->createInputProcessor($parsedXml, MPOINT_TYPE_HYDRO)->process();
			} catch (InputXmlProcessException $e) {
				//There are some invalid values in XML
				throw new UploadException(401);
			}
		} catch (UploadException $e) {
			//Create ErrorResponse xml
			http_response_code(400);

			return (new Response((new CreateErrorXml())->generateXml($e->getErrorXmlData())->asXML()))
				->setHeaders(['Content-type: application/xml']);
		} catch (Throwable $e) {
			//Create ErrorResponse xml
			http_response_code(500);

			return (new Response((new CreateErrorXml())->generateXml([new ErrorXmlData(500, $e->getMessage())])->asXML()))
				->setHeaders(['Content-type: application/xml']);
		}
	}


	/**
	 * Create input processor based on the mpoint type.
	 *
	 * @param SimpleXMLElement $xml  Parsed XML
	 * @param int              $type Type of upload request
	 *
	 * @return AbstractInputXmlProcessor
	 */
	protected function createInputProcessor(SimpleXMLElement $xml, int $type): AbstractInputXmlProcessor {
		switch ($type) {
			case MPOINT_TYPE_HYDRO:
				return new HydroInputXmlProcessor($xml);
			case MPOINT_TYPE_METEO:
				return new HydroInputXmlProcessor($xml);
		}
	}


	/**
	 * Find user based on auth header, and get the attached public key from database
	 *
	 * @return Identity
	 * @throws UploadException
	 */
	protected function getIdentity(): Identity {
		if ($this->identity === null) {
			$username = $this->getAuthHeaderParts()['keyId'] ?? null;

			if (!$username) {
				//Username is empty
				throw new UploadException(202);
			}

			try {
				//Find user in database
				$user = (new Select())
					->from('users')
					->where('username = :username')
					->addParameter('username', $username)
					->run(Query::FETCH_FIRST);
			} catch (QueryException $e) {
				//Query error
				throw UploadException::serverError();
			}

			if (!$user) {
				//User not found
				throw new UploadException(203);
			}

			try {
				//Find public key for user
				$publicKey = (new Select())
					->from('public_keys')
					->where('usersid = :userId')
					->where('revoked = :revoked')
					->setParameters([
						'userId'  => $user['id'],
						'revoked' => 0
					])
					->limit(1)
					->run(Query::FETCH_FIRST);
			} catch (QueryException $e) {
				//Query error
				throw UploadException::serverError();
			}

			//Create and identity from user
			$this->identity = Identity::createFromUser($user['id']);
			if ($publicKey) {
				//Set the public key
				$this->identity->setPublicKey($publicKey['public_key']);
			}
		}

		return $this->identity;
	}


}
