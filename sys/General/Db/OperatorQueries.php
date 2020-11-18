<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\Db\Query\Insert;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\Query\Update;
use Environet\Sys\General\EventLogger;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class OperatorQueries
 *
 * Useful queries for operators
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class OperatorQueries extends BaseQueries {

	/**
	 * @inheritDoc
	 */
	public static $tableName = 'operator';

	/**
	 * @inheritDoc
	 */
	public static $searchableFields = [
		'operator.name',
		'operator.address',
		'operator.email',
	];


	/**
	 * Get direct users, with ids as array keys
	 *
	 * @param int        $operatorId
	 *
	 * @param array|null $fieldsOnly
	 *
	 * @return array|null
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 */
	public static function getDirectUsers(int $operatorId, array $fieldsOnly = null) {
		$select = (new Select())->from('users');

		if ($fieldsOnly) {
			$fieldsOnly = array_map(function ($field) {
				return strpos($field, 'users.') !== 0 ? "users.$field" : $field;
			}, $fieldsOnly);
			$select->select($fieldsOnly);
		} else {
			$select->select(['users.*', '\'direct\' as connection_type']);
		}
		$select
			->join('operator_users', 'operator_users.usersid = users.id', Query::JOIN_LEFT)
			->where('operator_users.operatorid = :operatorId')
			->addParameter(':operatorId', $operatorId);

		return $select->run(Query::KEY_BY_ID);
	}


	/**
	 * Get all users of operator.
	 * It gets the directly attached users, and merge with the inherited (by groups) users.
	 * A new static key will be attached to users' data, it's the 'connection_type', which can be 'direct', 'group', and 'both'.
	 *
	 * @param int   $operatorId Id of the operator
	 * @param array $groupIds   Array of the operators' group ids
	 *
	 * @return array Array of merged users
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\OperatorQueries::getDirectUsers()
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 */
	public static function getMergedUsersOfOperator(int $operatorId, array $groupIds = []) {

		$users = self::getDirectUsers($operatorId);

		if ($groupIds) {
			//Get inherited group users with ids as array keys
			$groupUsers = (new Select())
				->from('users')
				->select(['users.*', '\'group\' as connection_type'])
				->join('users_groups', 'users_groups.usersid = users.id', Query::JOIN_LEFT)
				->whereIn('users_groups.groupsid', $groupIds, 'groupId')
				->run(Query::KEY_BY_ID);

			//Add inherited-only users to the $users array
			//and change connection type for users which either direct and group
			foreach ($groupUsers as $id => $groupUser) {
				if (isset($users[$id])) {
					$users[$id]['connection_type'] = 'both';
				} else {
					$users[$id] = $groupUser;
				}
			}
		}

		//Return the array of users
		return array_values($users);
	}


	/**
	 * Get operator's groups array
	 *
	 * @param int $operatorId Id of operator
	 *
	 * @return array
	 * @throws QueryException
	 */
	public static function getOperatorGroups(int $operatorId): array {
		return (new Select())
			->from('groups')
			->join('operator_groups', 'operator_groups.groupsid = groups.id', Query::JOIN_LEFT)
			->where('operator_groups.operatorid = :operatorId')
			->addParameter(':operatorId', $operatorId)
			->run();
	}


	/**
	 * Save permissions to specified operator.
	 *
	 * @param $values
	 * @param $idRight
	 *
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\BaseQueries::saveConnections()
	 */
	public static function saveUsers($values, $idRight) {
		parent::saveConnections($values, 'operator_users', 'usersid', 'operatorid', $idRight, true);
	}


	/**
	 * Save groups to specified operator.
	 *
	 * @param $values
	 * @param $idRight
	 *
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\BaseQueries::saveConnections()
	 */
	public static function saveGroups($values, $idRight) {
		parent::saveConnections($values, 'operator_groups', 'groupsid', 'operatorid', $idRight, true);
	}


	/**
	 * Update or insert an item in the operator table.
	 *
	 * @param array  $data       Data to save.
	 * @param mixed  $id         If the id is exist, update otherwise insert the new record.
	 * @param string $primaryKey The primary key of the specified table.
	 *
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Insert::run()
	 * @uses \Environet\Sys\General\Db\Query\Update::run()
	 * @uses \Environet\Sys\General\EventLogger::log()
	 * @uses \Environet\Sys\General\Db\OperatorQueries::saveGroups()
	 * @uses \Environet\Sys\General\Db\OperatorQueries::saveUsers()
	 */
	public static function save(array $data, $id = null, string $primaryKey = 'id') {
		$operatorData = [
			'name'       => $data['name'] ?? null,
			'address'    => $data['address'] ?? null,
			'email'      => $data['email'] ?? null,
			'phone'      => $data['phone'] ?? null,
			'url'        => $data['url'] ?? null,
			'other_info' => $data['other_info'] ?? null
		];

		if ($id) {
			// Update existing record and save operator data
			(new Update())
				->table('operator')
				->updateData($operatorData)
				->where('operator.id = :operatorId')
				->addParameter(':operatorId', $id)
				->run();

			EventLogger::log(EventLogger::EVENT_TYPE_OPERATOR_UPDATE, array_merge($operatorData, [
				'id' => $id
			]));

			// Connect user and groups with operator
			self::saveUsers($data['form_users'], $id);
			self::saveGroups($data['form_groups'], $id);
		} else {
			// Save operator data
			$operatorId = (new Insert())->table('operator')->addSingleData($operatorData)->run();

			EventLogger::log(EventLogger::EVENT_TYPE_OPERATOR_ADD, array_merge($operatorData, [
				'id' => $operatorId
			]));

			// Connect user and groups with operator
			self::saveUsers($data['form_users'], $operatorId);
			self::saveGroups($data['form_groups'], $operatorId);
		}
	}


	/**
	 * @inheritDoc
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\OperatorQueries::getOperatorGroups()
	 * @uses \Environet\Sys\General\Db\OperatorQueries::getMergedUsersOfOperator()
	 */
	public static function getById($id, string $primaryKey = 'id'): ?array {
		$record = parent::getById($id, $primaryKey);

		// Find groups of operator
		$record['merged_groups'] = self::getOperatorGroups($id);
		// Get direct, and inherited users
		$record['merged_users'] = self::getMergedUsersOfOperator($id, array_column($record['merged_groups'], 'id'));

		$record['form_users'] = (new Select())
			->select('usersid')
			->from('operator_users')
			->where('operatorid = :operatorId')
			->addParameter(':operatorId', $id)
			->run(Query::FETCH_COLUMN);
		$record['form_groups'] = (new Select())
			->select('groupsid')->from('operator_groups')
			->where('operatorid = :operatorId')
			->addParameter(':operatorId', $id)
			->run(Query::FETCH_COLUMN);

		return $record;
	}


}
