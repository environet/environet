<?php


namespace Environet\Sys\General;

use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\UserQueries;
use Environet\Sys\General\Exceptions\PermissionException;

/**
 * Class Identity
 *
 * A global auth-identity request. It can handle identities from multiple source (e.g user), and can be attached the requests
 *
 * @package Environet\Sys\General
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class Identity {

	const TYPE_USER = 'user';

	/**
	 * @var int Id of the identity
	 */
	private $id;

	/**
	 * @var string Identity type (e.g user)
	 */
	private $type;

	/**
	 * @var array Identity data from database
	 */
	private $data;

	/**
	 * @var string Attached public key of identity
	 */
	private $publicKey;

	/**
	 * @var string Attached permission list from database
	 */
	private $permissions = null;


	/**
	 * Identity constructor.
	 *
	 * @param string $type
	 * @param int    $id
	 * @param array  $data
	 */
	public function __construct(string $type, int $id, array $data) {
		$this->id = $id;
		$this->data = $data;
		$this->type = $type;
	}


	/**
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}


	/**
	 * Create an identity from a user
	 *
	 * @param int $userId
	 *
	 * @return Identity|null
	 */
	public static function createFromUser(int $userId): ?Identity {
		try {
			//Find the user, and create a new Identity with it
			$user = (new Select())
				->from('users')
				->where('id = :userId')
				->limit(1)
				->addParameter(':userId', $userId)
				->run(Query::FETCH_FIRST);

			return new static(self::TYPE_USER, $userId, $user);
		} catch (Exceptions\QueryException $e) {
			//Error during sql query
			return null;
		}

		//User not found
		return null;
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
	 * @throws PermissionException
	 */
	public function getPermissions(): array {
		if ($this->type !== self::TYPE_USER) {
			throw new PermissionException('Invalid identity type for permission check');
		}

		if ($this->permissions === null) {
			$this->permissions = UserQueries::getUserPermissions($this->id);
		}

		return $this->permissions;
	}


}
