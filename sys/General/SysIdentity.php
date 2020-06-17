<?php


namespace Environet\Sys\General;

/**
 * Class SysIdentity
 *
 * Identity for System itself. It is used when the system calls itself with http request
 *
 * @package Environet\Sys\General
 * @author  SRG Group <dev@srg.hu>
 */
class SysIdentity extends Identity {

	const SYS_KEY_DIR = SRC_PATH . '/data/sys_keys';


	/**
	 * Identity constructor.
	 */
	public function __construct() {
		parent::__construct(0, []);
	}


	/**
	 * Get identity id, it's the username of sys user
	 *
	 * @return int
	 */
	public function getId() {
		return SYS_USERNAME;
	}


	/**
	 * Get the identity's attached public key, or it's not set, use the locally stored public key
	 *
	 * @return string|null
	 */
	public function getPublicKey(): ?string {
		if (!is_null($this->publicKey)) {
			return $this->publicKey;
		}
		return file_exists(self::getSysPublicKeyFile()) ? file_get_contents(self::getSysPublicKeyFile()) : null;
	}


	/**
	 * Gets permissions of sys user. It's empty, because it is a superadmin
	 *
	 * @return array
	 * @uses \Environet\Sys\General\Db\UserQueries::getUserPermissions()
	 */
	public function getPermissions(): array {
		return [];
	}


	/**
	 * Sys user is a super admin
	 *
	 * @return bool
	 */
	protected function isSuperAdmin(): bool {
		return true;
	}


	/**
	 * Standard location of private key file
	 *
	 * @return string
	 */
	public static function getSysPrivateKeyFile() {
		return self::SYS_KEY_DIR . '/private.pem';
	}


	/**
	 * Standard location of public key file
	 *
	 * @return string
	 */
	public static function getSysPublicKeyFile() {
		return self::SYS_KEY_DIR . '/public.pem';
	}


}
