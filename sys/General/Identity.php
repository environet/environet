<?php


namespace Environet\Sys\General;

use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\UserQueries;

/**
 * Class Identity
 *
 * A global auth-identity request. It can handle identities from multiple source (e.g user), and can be attached the requests
 *
 * @package Environet\Sys\General
 * @author  SRG Group <dev@srg.hu>
 */
class Identity {

	const ADMIN_PERMISSION = 'admin.all';

	/**
	 * @var int Id of the identity
	 */
	protected $id;

	/**
	 * @var array Identity data from database
	 */
	protected $data;

	/**
	 * @var string Attached public key of identity
	 */
	protected $publicKey;

	/**
	 * @var array The permissions of this identity
	 */
	protected $permissions;

	/**
	 * @var array Permissions this identity has been granted
	 */
	protected $authorizedPermissions = [];


	/**
	 * Identity constructor.
	 *
	 * @param int   $id
	 * @param array $data
	 */
	public function __construct(int $id, array $data) {
		$this->id = $id;
		$this->data = $data;
	}


	/**
	 * Get identity id.
	 *
	 * @return int|string
	 */
	public function getId() {
		return $this->id;
	}


	/**
	 * Get identity data.
	 *
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}


	/**
	 * Create an identity from a user.
	 *
	 * @param int $userId
	 *
	 * @return Identity|null
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 */
	public static function createFromUser(int $userId): ?Identity {
		try {
			// Find the user, and create a new Identity with it
			$user = (new Select())
				->from('users')
				->where('id = :userId')
				->limit(1)
				->addParameter(':userId', $userId)
				->run(Query::FETCH_FIRST);


			$identity = new static($userId, $user);
			$identity->data['is_super_admin'] = $identity->isSuperAdmin();

			return $identity;
		} catch (Exceptions\QueryException $e) {
			// Error during sql query
			return null;
		}
	}


	/**
	 * Get the identity's attached public key
	 * @return string|null
	 */
	public function getPublicKey(): ?string {
		return $this->publicKey;
	}


	/**
	 * Set a public key string
	 *
	 * @param string $publicKey
	 *
	 * @return Identity
	 */
	public function setPublicKey(string $publicKey): Identity {
		$this->publicKey = $publicKey;

		return $this;
	}


	/**
	 * Gets and stores a given user's permissions (on the instance).
	 *
	 * @return array
	 * @throws Exceptions\QueryException
	 * @uses \Environet\Sys\General\Db\UserQueries::getUserPermissions()
	 */
	public function getPermissions(): array {
		if ($this->permissions === null) {
			$this->permissions = UserQueries::getUserPermissions($this->id);
		}

		return $this->permissions;
	}


	/**
	 * @return bool
	 * @throws Exceptions\QueryException
	 */
	public function isSuperAdmin(): bool {
		return in_array(self::ADMIN_PERMISSION, $this->getPermissions());
	}


	/**
	 * Checks the identity against a list of permissions
	 *
	 * @param array $permissions
	 *
	 * @return bool
	 * @throws Exceptions\QueryException
	 */
	public function hasPermissions(array $permissions): bool {
		if ($this->isSuperAdmin()) {
			return true;
		}

		return !array_diff($permissions, $this->getPermissions());
	}


	/**
	 * @param array $permissions
	 *
	 * @return bool
	 * @throws Exceptions\QueryException
	 */
	public function hasPermissionsAnyOf(array $permissions): bool {
		if ($this->isSuperAdmin()) {
			return true;
		}

		return (bool) array_intersect($permissions, $this->getPermissions());
	}


	public function setAuthorizedPermissions(array $permissions) {
		$this->authorizedPermissions = $permissions;
	}


	public function getAuthorizedPermissions(): array {
		return $this->authorizedPermissions;
	}


}
