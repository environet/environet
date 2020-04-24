<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\Db\Query\Insert;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\Query\Update;
use Environet\Sys\General\EventLogger;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class GroupQueries
 *
 * Useful queries for groups
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class GroupQueries extends BaseQueries {

	/**
	 * @inheritDoc
	 */
	public static $tableName = 'groups';

	/**
	 * @inheritDoc
	 */
	public static $searchableFields = [
		'groups.name'
	];


	/**
	 * Save group's data.
	 * If the id exists, update the record, otherwise insert new record.
	 *
	 * @param array  $data
	 * @param mixed  $id
	 * @param string $primaryKey
	 *
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Insert::run()
	 * @uses \Environet\Sys\General\Db\Query\Update::run()
	 * @uses \Environet\Sys\General\EventLogger::log()
	 * @uses \Environet\Sys\General\Db\GroupQueries::savePermissions()
	 */
	public static function save(array $data, $id = null, string $primaryKey = 'id') {
		$dataToRun = [
			'name' => $data['name']
		];

		if ($id) {
			EventLogger::log(EventLogger::EVENT_TYPE_GROUP_UPDATE, array_merge($dataToRun, [
				'id' => $id
			]));

			// update group
			(new Update())
				->table('groups')
				->where('id = :groupId')
				->updateData($dataToRun)
				->addParameter(':groupId', $id)
				->run();

			self::savePermissions($data['permissions'] ?? [], $id);
		} else {
			// insert new record
			$insertId = (new Insert())
				->table('groups')
				->addSingleData($dataToRun)
				->run();

			EventLogger::log(EventLogger::EVENT_TYPE_GROUP_ADD, array_merge($dataToRun, [
				'id' => $insertId
			]));

			// add permission relation to group
			self::savePermissions($data['permissions'] ?? [], $insertId);
		}
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
		parent::saveConnections($values, "group_permissions", "permissionsid", "groupsid", $idRight, true);
	}


	/**
	 * @inheritDoc
	 */
	public static function getById($id, string $primaryKey = 'id'): ?array {
		$record = parent::getById($id, $primaryKey);

		$record['permissions'] = (new Select())
			->select('permissionsid')
			->from('group_permissions')
			->where('groupsid = :groupId')
			->addParameter(':groupId', $id)
			->run(Query::FETCH_COLUMN);

		return $record;
	}


}
