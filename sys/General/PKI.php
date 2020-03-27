<?php

namespace Environet\Sys\General;

use Environet\Sys\General\Exceptions\PKIException;

/**
 * Class PKI
 *
 * Helper functions for Public Key Infrastructure
 *
 * @package Environet\Sys\General
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class PKI {


	/**
	 * Create a base64 encoded signature for a content with openssl_sign based on a private key
	 *
	 * @param string $content
	 * @param string $privateKey
	 *
	 * @return string
	 * @throws PKIException
	 */
	public function generateSignature(string $content, string $privateKey): string {
		if (openssl_sign($content, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
			//Signature created successfully
			return base64_encode($signature);
		} else {
			//Error while creating signature
			throw PKIException::openSSLError();
		}
	}


	/**
	 * Build a standard authorization header with username and signature.
	 * Username will be added as "keyId" to the header
	 *
	 * @param string $signature Base64 encoded signature
	 * @param string $username
	 *
	 * @return string
	 */
	public function authHeaderWithSignature(string $signature, string $username): string {
		return "Signature keyId=\"$username\",algorithm=\"rsa-sha256\",signature=\"$signature\"";
	}


}