<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\Db\Query\Insert;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\Query\Update;
use Environet\Sys\General\EventLogger;
use Environet\Sys\General\Exceptions\MissingEventTypeException;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class UserQueries
 *
 * Useful queries for users
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class UserQueries extends BaseQueries {

	/**
	 * @inheritDoc
	 */
	public static $tableName = 'users';

	/**
	 * @inheritDoc
	 */
	public static $searchableFields = [
		'users.name',
		'users.username',
		'users.email'
	];


	/**
	 * Save user's data.
	 * If id exists, update the record, otherwise insert new record.
	 * Also saves the user's permissions and groups.
	 *
	 * @param array  $data
	 * @param mixed  $id
	 * @param string $primaryKey
	 *
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Insert::run()
	 * @uses \Environet\Sys\General\Db\Query\Update::run()
	 * @uses \Environet\Sys\General\EventLogger::log()
	 * @uses \Environet\Sys\General\Db\UserQueries::saveGroups()
	 * @uses \Environet\Sys\General\Db\UserQueries::savePermissions()
	 */
	public static function save(array $data, $id = null, string $primaryKey = 'id') {
		if ($id) {
			// Update user
			if ($data['public_key'] !== "") {
				// Revoke the previous keys
				(new Update())
					->table('public_keys')
					->where('usersid = :userId')
					->updateData([
						'revoked'    => true,
						'revoked_at' => date('Y-m-d H:i:s')
					])
					->addParameter(':userId', $id)
					->run();

				// Add new
				(new Insert())
					->table('public_keys')
					->addSingleData([
						'usersId'    => $id,
						'public_key' => $data['public_key'],
					])
					->run();
			}
			// User data to update
			$userUpdateData = [
				'name'  => $data['name'],
				'email' => $data['email']
			];

			// Log user update event data
			EventLogger::log(EventLogger::EVENT_TYPE_USER_UPDATE, array_merge($userUpdateData, [
				'id' => $id
			]));

			if ($data['password'] !== "") {
				// If new password is exists
				$userUpdateData = array_merge($userUpdateData, [
					'password' => password_hash($data['password'], PASSWORD_DEFAULT)
				]);
			}

			// If data is valid, update user
			(new Update())
				->table('users')
				->where('id = :userId')
				->updateData($userUpdateData)
				->addParameter(':userId', $id)
				->run();

			self::savePermissions($data['form_permissions'], $id);
			self::saveGroups($data['form_groups'], $id);
		} else {
			// Add new user
			$insertId = (new Insert())
				->table('users')
				->addSingleData([
					'name'     => $data['name'],
					'username' => $data['username'],
					'email'    => $data['email'],
					'password' => password_hash($data['password'], PASSWORD_DEFAULT),
				])
				->run();
			// Add new public_key
			(new Insert())
				->table('public_keys')
				->addSingleData([
					'usersId'    => $insertId,
					'public_key' => $data['public_key'],
				])
				->run();

			// Log user add event data
			EventLogger::log(EventLogger::EVENT_TYPE_USER_ADD, [
				'name'     => $data['name'],
				'username' => $data['username'],
				'email'    => $data['email'],
				'id'       => $insertId
			]);

			self::savePermissions($data['form_permissions'], $insertId);
			self::saveGroups($data['form_groups'], $insertId);
		}
	}


	/**
	 * Get all operators of the user.
	 * It gets the directly attached operators, and merge with the inherited (by groups) operators.
	 * A new static key will be attached to operator's data, it's the 'connection_type', which can be 'direct', 'group', and 'both'.
	 *
	 * @param int   $userId   Id of the user
	 * @param array $groupIds Array of the user's group ids
	 *
	 * @return array Array of merged operators
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 */
	public static function getMergedOperatorsOfUser(int $userId, array $groupIds = []) {
		// Get direct operators, with ids as array keys
		$operators = (new Select())
			->select(['operator.*', '\'direct\' as connection_type'])
			->from('operator')
			->join('operator_users', 'operator_users.operatorid = operator.id', Query::JOIN_LEFT)
			->where('operator_users.usersid = :userId')
			->addParameter(':userId', $userId)
			->run(Query::KEY_BY_ID);

		if ($groupIds) {
			// Get inherited group operators with ids as array keys
			$groupOperators = (new Select())
				->from('operator')
				->select(['operator.*', '\'group\' as connection_type'])
				->join('operator_groups', 'operator_groups.operatorid = operator.id', Query::JOIN_LEFT)
				->whereIn('operator_groups.groupsid', $groupIds, 'groupId')
				->run(Query::KEY_BY_ID);

			// Add inherited-only operators to the $operators array and change connection type for operators which either direct and group
			foreach ($groupOperators as $id => $groupOperator) {
				if (isset($operators[$id])) {
					$operators[$id]['connection_type'] = 'both';
				} else {
					$operators[$id] = $groupOperator;
				}
			}
		}

		// Return the array of operators
		return array_values($operators);
	}


	/**
	 * Get user permissions (both individually set and group inherited ones)
	 *
	 * @param int $usedId
	 *
	 * @return array
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 */
	public static function getUserPermissions(int $usedId): array {
		$result = [];
		$rows = (new Select())
			->select('permissions.name as permission')
			->from('permissions')
			->join('group_permissions gp', 'permissions.id = gp.permissionsid')
			->join('users_groups ug', 'gp.groupsid = ug.groupsid')
			->join('users u', 'ug.usersid = u.id')
			->where("u.id = {$usedId}")
			->union(
				(new Select())
					->select('permissions.name as permission')
					->from('permissions')
					->join('user_permissions up', 'permissions.id = up.permissionsid')
					->join('users u2', 'up.usersid = u2.id')
					->where("u2.id = {$usedId}")
			)
			->run();

		foreach ($rows as $row) {
			$result[] = $row['permission'];
		}

		return $result;
	}


	/**
	 * Get user groups array
	 *
	 * @param int $userId Id of user
	 *
	 * @return array
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 */
	public static function getUserGroups(int $userId): array {
		return (new Select())
			->from('groups')
			->join('users_groups', 'users_groups.groupsid = groups.id', Query::JOIN_LEFT)
			->where('users_groups.usersid = :userId')
			->addParameter(':userId', $userId)
			->run();
	}


	/**
	 * Save permissions to specified user.
	 *
	 * @param $values
	 * @param $idRight
	 *
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\BaseQueries::saveConnections()
	 */
	public static function savePermissions($values, $idRight) {
		parent::saveConnections($values, "user_permissions", "permissionsid", "usersid", $idRight, true);
	}


	/**
	 * Save groups to specified user.
	 *
	 * @param $values
	 * @param $idRight
	 *
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\BaseQueries::saveConnections()
	 */
	public static function saveGroups($values, $idRight) {
		parent::saveConnections($values, "users_groups", "groupsid", "usersid", $idRight, true);
	}


	/**
	 * Soft delete.
	 *
	 * @param int    $id
	 * @param bool   $soft
	 * @param string $primaryKey
	 *
	 * @throws QueryException
	 * @throws MissingEventTypeException
	 * @uses \Environet\Sys\General\Db\BaseQueries::delete()
	 */
	public static function delete(int $id, bool $soft = false, string $primaryKey = 'id') {
		parent::delete($id, true);
	}


	/**
	 * @inheritDoc
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\UserQueries::getUserGroups()
	 * @uses \Environet\Sys\General\Db\UserQueries::getMergedOperatorsOfUser()
	 */
	public static function getById($id, string $primaryKey = 'id'): ?array {
		$record = parent::getById($id, $primaryKey);

		// Find groups of the user
		$record['show_groups'] = UserQueries::getUserGroups($record['id']);

		// Get direct and inherited operators
		$record['show_operators'] = UserQueries::getMergedOperatorsOfUser($record['id'], array_column($record['show_groups'], 'id'));

		// Get a list of public keys
		$record['show_publicKeys'] = (new Select())
			->from('public_keys')
			->where('usersid = :userId')
			->addParameter(':userId', $record['id'])
			->run();

		$record['form_permissions'] = (new Select())
			->select('permissionsid')
			->from('user_permissions')
			->where('usersid = :userId')
			->addParameter(':userId', $record['id'])
			->run(Query::FETCH_COLUMN);

		$record['form_groups'] = (new Select())
			->select('groupsid')
			->from('users_groups')
			->where('usersid = :userId')
			->addParameter(':userId', $record['id'])
			->run(Query::FETCH_COLUMN);

		return $record;
	}


	/**
	 * @inheritDoc
	 */
	public static function getDeleteEventType(): string {
		return EventLogger::EVENT_TYPE_USER_DELETE;
	}


}
