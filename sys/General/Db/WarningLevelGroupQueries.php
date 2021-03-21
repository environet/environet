<?php

namespace Environet\Sys\General\Db;

/**
 * Class WarningLevelGroupQueries
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class WarningLevelGroupQueries extends BaseQueries {

	/**
	 * @inheritDoc
	 */
	public static $tableName = 'warning_level_groups';

	/**
	 * @inheritDoc
	 */
	public static $searchableFields = [
		'warning_level_groups.name',
	];


	/**
	 * @param array $data
	 *
	 * @return array
	 */
	public static function prepareData(array $data): array {
		return [
			'name' => $data['name'],
		];
	}


	/**
	 * @inheritDoc
	 */
	public static function getInsertEventType(): string {
		return 'warning_level_group_add';
	}


	/**
	 * @inheritDoc
	 */
	public static function getDeleteEventType(): string {
		return 'warning_level_group_delete';
	}


	/**
	 * @inheritDoc
	 */
	public static function getUpdateEventType(): string {
		return 'warning_level_group_update';
	}


}
