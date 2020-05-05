<?php

namespace Environet\Sys\General\HttpClient;

use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\ApiException;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Identity;
use Environet\Sys\General\SysIdentity;

/**
 * Class ApiHandler
 *
 * Abstract class for API related handlers
 *
 * @package Environet\Sys\General\HttpClient
 * @author  SRG Group <dev@srg.hu>
 */
abstract class ApiHandler extends BaseHandler {

	/**
	 * @var null|array The parsed authorization header
	 */
	protected $authHeaderParts = null;

	/**
	 * @var null|Identity The identity of the current request
	 */
	protected $identity = null;


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
	 * Parse auth header which contains the signature, the username (as keyId) and the algorithm of signature.
	 * Example auth header: keyId="test.user",algorithm="rsa-sha256",signature=""
	 *
	 * @param string $authHeader
	 *
	 * @return array|null
	 */
	protected function parseAuthHeader(string $authHeader): ?array {
		// Example auth header: keyId="test.user",algorithm="rsa-sha256",signature=""
		if (preg_match('/^Signature\s(.*)$/i', $authHeader, $match)) {
			$parts = [];
			foreach (explode(',', $match[1]) as $value) {
				if (preg_match('/^(.*)\="(.*)"$/', $value, $match)) {
					$parts[trim($match[1])] = $match[2];
				}
			}

			// Format is valid
			return $parts;
		}

		// Invalid header format
		return null;
	}


	/**
	 * Prepare and parse the Authorization header
	 *
	 * @return array
	 * @throws ApiException
	 */
	protected function getAuthHeaderParts(): array {
		if ($this->authHeaderParts === null) {
			// If the header isn't set, the request is invalid
			if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
				throw new ApiException(201);
			}

			// Split the auth header to parts. For format see \Environet\Sys\General\PKI::authHeaderWithSignature
			$this->authHeaderParts = $this->parseAuthHeader($_SERVER['HTTP_AUTHORIZATION']);

			// If the parts aren't present, the request is invalid
			if ($this->authHeaderParts === null) {
				throw new ApiException(204);
			}
		}

		return $this->authHeaderParts;
	}


	/**
	 * Find user based on auth header, and get the attached public key from database
	 *
	 * @return Identity
	 * @throws ApiException
	 * @uses \Environet\Sys\Upload\UploadHandler::getAuthHeaderParts()
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 * @uses \Environet\Sys\General\Identity::createFromUser()
	 */
	protected function getIdentity(): Identity {
		if ($this->identity === null) {
			$username = $this->getAuthHeaderParts()['keyId'] ?? null;

			if (!$username) {
				// Username is empty
				throw new ApiException(202);
			}

			if ($username === SYS_USERNAME && gethostbyname('dist_php') === $_SERVER['REMOTE_ADDR']) {
				//If user name is the sys username, and the request is called from the php container (so localhost) use the SysIdentity
				$this->identity = new SysIdentity();
			} else {
				//Get identity from auth header, it will be a standard user
				try {
					// Find user in database
					$user = (new Select())
						->from('users')
						->where('username = :username')
						->addParameter('username', $username)
						->run(Query::FETCH_FIRST);
				} catch (QueryException $e) {
					// Query error
					throw ApiException::serverError();
				}

				if (!$user) {
					// User not found
					throw new ApiException(203);
				}

				try {
					// Find public key for user
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
					// Query error
					throw ApiException::serverError();
				}

				// Create and identity from user
				$this->identity = Identity::createFromUser($user['id']);
				if ($publicKey) {
					// Set the public key
					$this->identity->setPublicKey($publicKey['public_key']);
				}
			}
			$this->request->setIdentity($this->identity);
		}

		return $this->identity;
	}


	/**
	 * Authorize the incoming API request
	 *
	 * Does the following steps:
	 * 1. Gets the user identity and the public key stored with it.
	 * 2. Validates the signature parsed from the authorization header and validates it with the provided token from the request and the public key.
	 *
	 * @param array $requiredPermissions
	 * @throws ApiException
	 * @throws QueryException
	 */
	protected function authorizeRequest(array $requiredPermissions = []): void {
		// Get the identity based on auth header
		$identity = $this->getIdentity();

		$publicKey = $identity->getPublicKey();
		// Only users with public keys are allowed
		if (!$publicKey) {
			throw new ApiException(206);
		}

		$this->validateSignature();

		if (!$this->getIdentity()->hasPermissions($requiredPermissions)) {
			throw new ApiException(205);
		} else {
			$this->getIdentity()->setAuthorizedPermissions($requiredPermissions);
		}
	}


	/**
	 * Validate the request signature in the auth header, against the token query parameter
	 *
	 * @throws ApiException
	 */
	protected function validateSignature() {
		$token = $this->request->getQueryParam('token', false);
		if (!$token) {
			throw new ApiException(207);
		}

		$signature = base64_decode($this->getAuthHeaderParts()['signature'] ?? '');

		$signatureValid = @openssl_verify($token, $signature, $this->identity->getPublicKey(), OPENSSL_ALGO_SHA256);

		if (!$signatureValid) {
			throw new ApiException(208);
		}
	}


}
