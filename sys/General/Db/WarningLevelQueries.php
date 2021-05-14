<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;

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


	/**
	 * Get a simple list of records. Keys are the id, label is configurable
	 *
	 * @param int $operatorId
	 *
	 * @return array|null
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 * @uses \exception_logger()
	 */
	public static function getOptionListForOperator(int $operatorId): ?array {
		try {
			$records = (new Select())
				->from(static::$tableName)
				->select(["CONCAT(warning_levels.short_description, ' (',  warning_level_groups.name, ')') as name", "warning_levels.id as id"])
				->orderBy('warning_level_groups.id', 'ASC')
				->join('warning_level_groups', 'warning_level_groups.id = warning_levels.warning_level_groupid')
				->where('warning_levels.operatorid = :operatorId')
				->addParameter('operatorId', $operatorId)
				->run();
			$records = array_combine(array_column($records, 'id'), array_column($records, 'name'));

			return $records ?: [];
		} catch (QueryException $e) {
			exception_logger($e);

			return [];
		}
	}


}
