<?php

namespace Environet\Sys\General;

use Environet\Sys\General\Exceptions\PKIException;
use Exception;

/**
 * Class PKI
 *
 * Helper class for Public Key Infrastructure management.
 *
 * @package Environet\Sys\General
 * @author  SRG Group <dev@srg.hu>
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
	 * @uses \openssl_sign()
	 */
	public function generateSignature(string $content, string $privateKey): string {
		if (openssl_sign($content, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
			// Signature created successfully
			return base64_encode($signature);
		} else {
			// Error while creating signature
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


	/**
	 * Generate openssl keypair, and return it as strings
	 *
	 * @throws Exception
	 */
	public function generateKeyPair(): array {
		// Generate key pair
		$result = openssl_pkey_new([
			'digest_alg'       => 'sha256',
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		]);
		openssl_pkey_export($result, $privateKey);
		$publicKey = openssl_pkey_get_details($result)['key'] ?? null;

		// Check if generated successfully
		if (!($publicKey && $privateKey)) {
			throw new Exception('Couldn\'t generate key pair.');
		}

		return [$publicKey, $privateKey];
	}


}