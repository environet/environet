<?php


namespace Environet\Sys\Download;

use Environet\Sys\Download\Exceptions\DownloadException;
use Environet\Sys\General\Db\MonitoringPointQueries;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\ApiException;
use Environet\Sys\General\Exceptions\PermissionException;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\HttpClient\ApiHandler;
use Environet\Sys\General\Identity;
use Environet\Sys\General\Response;
use Environet\Sys\Xml\CreateErrorXml;
use Environet\Sys\Xml\CreateOutputXml;
use Environet\Sys\Xml\Model\ErrorXmlData;
use Throwable;

/**
 * Class DownloadHandler
 *
 * Handles download API requests
 *
 * @package Environet\Sys\Download
 * @author  SRG Group <dev@srg.hu>
 */
class DownloadHandler extends ApiHandler {

	/**
	 * @inheritDoc
	 */
	protected const HANDLER_PERMISSION = 'api.download';

	/**
	 * @var null|array The parsed authorization header
	 */
	protected $authHeaderParts = null;

	/**
	 * @var null|Identity The current downloader's identity
	 */
	protected $identity = null;


	/**
	 * Prepare and parse the Authorization header
	 *
	 * @return array
	 * @throws DownloadException
	 */
	protected function getAuthHeaderParts(): array {
		if ($this->authHeaderParts === null) {
			// If the header isn't set, the request is invalid
			if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
				throw new DownloadException(201);
			}

			// Split the auth header to parts. For format see \Environet\Sys\General\PKI::authHeaderWithSignature
			$this->authHeaderParts = $this->parseAuthHeader($_SERVER['HTTP_AUTHORIZATION']);

			// If the parts aren't present, the request is invalid
			if ($this->authHeaderParts === null) {
				throw new DownloadException(204);
			}
		}

		return $this->authHeaderParts;
	}


	/**
	 * @inheritDoc
	 * @return Identity
	 * @throws DownloadException
	 * @throws ApiException
	 */
	protected function getIdentity(): Identity {
		if ($this->identity === null) {
			$username = $this->getAuthHeaderParts()['keyId'] ?? null;

			if (!$username) {
				// Username is empty
				throw new DownloadException(202);
			}

			try {
				// Find user in database
				$user = (new Select())
					->from('users')
					->where('username = :username')
					->addParameter('username', $username)
					->run(Query::FETCH_FIRST);
			} catch (QueryException $e) {
				//Query error
				throw DownloadException::serverError();
			}

			if (!$user) {
				//User not found
				throw new DownloadException(203);
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
				throw DownloadException::serverError();
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


	/**
	 * @inheritDoc
	 *
	 * @return void
	 * @throws PermissionException
	 * @throws QueryException
	 * @throws DownloadException
	 * @throws ApiException
	 */
	protected function authorizeRequest(): void {
		if (!in_array(self::HANDLER_PERMISSION, $this->getIdentity()->getPermissions())) {
			throw new DownloadException(205);
		}
	}


	/**
	 * Fetch and sanitize a request query array parameter.
	 *
	 * @param string $key
	 *
	 * @return array
	 */
	protected function parseArrayParam(string $key) {
		$params = $this->request->getQueryParam($key, []);
		if ($params) {
			if (!is_array($params)) {
				$params = [$params];
			}

			foreach ($params as &$param) {
				$param = filter_var($param, FILTER_SANITIZE_STRING);
			}

			return $params;
		}

		return [];
	}


	/**
	 * Handle the incoming download request.
	 *
	 * Does the following steps:
	 * 1. Gets the user identity via {@see DownloadHandler::getIdentity()} and the public key stored with it.
	 * 2. Validates the signature parsed from the authorization header ({@see DownloadHandler::getAuthHeaderParts()}) and validates it with the provided token from the request and the public key.
	 * 3. Fetches and validates the required parameter "type" of the monitoring points requested (hydro or meteo).
	 * 4. Parses and applies optional filter parameters start, end, country and symbol.
	 * 5. Queries the results and generates the output XML containing them.
	 *
	 * @return Response|mixed
	 * @uses \Environet\Sys\Download\DownloadHandler::getIdentity()
	 * @uses \Environet\Sys\Download\DownloadHandler::getAuthHeaderParts()
	 * @uses \Environet\Sys\General\Db\MonitoringPointQueries::getBuilder()
	 * @uses \Environet\Sys\General\Db\MonitoringPointQueries::getResults()
	 * @uses \Environet\Sys\Xml\CreateOutputXml::generateXml()
	 */
	public function handleRequest() {
		try {
			// Get the identity based on auth header
			$identity = $this->getIdentity();

			$publicKey = $identity->getPublicKey();
			// Only users with public keys are allowed
			if (!$publicKey) {
				throw new DownloadException(206);
			}

			$this->authorizeRequest();

			// Request signature check
			$token = $this->request->getQueryParam('token', false);
			if (!$token) {
				throw new DownloadException(207);
			}

			$signature = base64_decode($this->getAuthHeaderParts()['signature'] ?? '');
			$signatureValid = openssl_verify($token, $signature, $publicKey, OPENSSL_ALGO_SHA256);

			if (!$signatureValid) {
				// Signature is not valid
				throw new DownloadException(301);
			}

			// Observation point type check
			$type = $this->request->getQueryParam('type', false);
			if (!$type) {
				// Missing type
				throw new DownloadException(302);
			}
			$type = filter_var($type, FILTER_SANITIZE_STRING);
			if (!in_array($type, [MonitoringPointQueries::TYPE_HYDRO, MonitoringPointQueries::TYPE_METEO])) {
				// Invalid type
				throw new DownloadException(303);
			}

			// Every check passed, start preparing the response
			$queryBuilder = MonitoringPointQueries::getBuilder();
			$queryBuilder->setType($type);

			// Optional filters
			$startTime = $this->request->getQueryParam('start', false);
			if ($startTime) {
				try {
					$queryBuilder->setStartTime(new \DateTime(filter_var($startTime, FILTER_SANITIZE_STRING)));
				} catch (Throwable $e) {
					throw new DownloadException(304);
				}
			}

			$endTime = $this->request->getQueryParam('end', false);
			if ($endTime) {
				try {
					$queryBuilder->setEndTime(new \DateTime(filter_var($endTime, FILTER_SANITIZE_STRING)));
				} catch (Throwable $e) {
					throw new DownloadException(305);
				}
			}

			$queryBuilder->setCountries($this->parseArrayParam('country'));
			$queryBuilder->setObservedProperties($this->parseArrayParam('symbol'));

			return (new Response((new CreateOutputXml())->generateXml($queryBuilder->getResults())->asXML()))->setHeaders(['Content-type: application/xml']);
		} catch (DownloadException $e) {
			// Create ErrorResponse xml
			http_response_code(400);

			return (new Response((new CreateErrorXml())->generateXml($e->getErrorXmlData())->asXML()))->setHeaders(['Content-type: application/xml']);
		} catch (Throwable $e) {
			// Create ErrorResponse xml
			http_response_code(500);

			return (new Response((new CreateErrorXml())->generateXml([new ErrorXmlData(500, $e->getMessage())])->asXML()))
				->setHeaders(['Content-type: application/xml']);
		}
	}


}
