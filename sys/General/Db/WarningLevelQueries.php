<?php

namespace Environet\Sys\General\Db;

/**
 * Class WarningLevelQueries
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class WarningLevelQueries extends BaseQueries {

	/**
	 * @inheritDoc
	 */
	public static $tableName = 'warning_levels';


	/**
	 * @param array $data
	 *
	 * @return array
	 */
	public static function prepareData(array $data): array {
		return [
			'operatorid'            => $data['operatorid'],
			'warning_level_groupid' => $data['warning_level_groupid'],
			'color'                 => $data['color'] ?? null,
			'short_description'     => $data['short_description'],
			'long_description'      => $data['long_description'] ?? null,
			'is_inclusive'          => $data['is_inclusive'] ?? true,
		];
	}


	/**
	 * @inheritDoc
	 */
	public static function getInsertEventType(): string {
		return 'warning_level_add';
	}


	/**
	 * @inheritDoc
	 */
	public static function getDeleteEventType(): string {
		return 'warning_level_delete';
	}


	/**
	 * @inheritDoc
	 */
	public static function getUpdateEventType(): string {
		return 'warning_level_update';
	}


}
