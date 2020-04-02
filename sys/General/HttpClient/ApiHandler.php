<?php

namespace Environet\Sys\General\HttpClient;

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
					$parts[$match[1]] = $match[2];
				}
			}

			// Format is valid
			return $parts;
		}

		// Invalid header format
		return null;
	}


}
