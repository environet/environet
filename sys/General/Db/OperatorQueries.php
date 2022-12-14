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
	 * It gets the directly attached users
	 * A new static key will be attached to users' data, it's the 'connection_type', which can be 'direct'.
	 *
	 * @param int   $operatorId Id of the operator
	 *
	 * @return array Array of merged users
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\OperatorQueries::getDirectUsers()
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 */
	public static function getMergedUsersOfOperator(int $operatorId) {

		$users = self::getDirectUsers($operatorId);

		//Return the array of users
		return array_values($users);
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
	 * @uses \Environet\Sys\General\Db\OperatorQueries::saveUsers()
	 */
	public static function save(array $data, $id = null, string $primaryKey = 'id', array $record = null) {
		$operatorData = [
			'name'       => $data['name'] ?? null,
			'address'    => $data['address'] ?? null,
			'email'      => $data['email'] ?? null,
			'phone'      => $data['phone'] ?? null,
			'url'        => $data['url'] ?? null,
			'other_info' => $data['other_info'] ?? null
		];

		$changes = [];
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

			// Connect user with operator
			self::saveUsers($data['form_users'], $id);
		} else {
			// Save operator data
			$id = (new Insert())->table('operator')->addSingleData($operatorData)->run();

			EventLogger::log(EventLogger::EVENT_TYPE_OPERATOR_ADD, array_merge($operatorData, [
				'id' => $id
			]));

			// Connect user with operator
			self::saveUsers($data['form_users'], $id);
		}

		return [$id, $changes];
	}


	/**
	 * @inheritDoc
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\OperatorQueries::getMergedUsersOfOperator()
	 */
	public static function getById($id, string $primaryKey = 'id'): ?array {
		$record = parent::getById($id, $primaryKey);

		// Get direct, and inherited users
		$record['merged_users'] = self::getMergedUsersOfOperator($id);

		$record['form_users'] = (new Select())
			->select('usersid')
			->from('operator_users')
			->where('operatorid = :operatorId')
			->addParameter(':operatorId', $id)
			->run(Query::FETCH_COLUMN);

		return $record;
	}


}
