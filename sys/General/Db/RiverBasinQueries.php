<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\Db\Query\Delete;
use Environet\Sys\General\Db\Query\Update;
use Environet\Sys\General\EventLogger;
use Environet\Sys\General\Exceptions\MissingEventTypeException;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class RiverBasinQueries
 *
 * Base queries adjusted for river basins
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class RiverBasinQueries extends BaseQueries {

	/**
	 * @inheritdoc
	 */
	public static $tableName = 'river_basin';

	/**
	 * @inheritDoc
	 */
	public static $searchableFields = [
		'river_basin.name',
	];


	/**
	 * @inheritDoc
	 */
	public static function prepareData(array $data): array {
		return $data;
	}


	/**
	 * @inheritDoc
	 */
	public static function getUpdateEventType(): string {
		return EventLogger::EVENT_TYPE_RIVER_BASIN_UPDATE;
	}


	/**
	 * @inheritDoc
	 */
	public static function getInsertEventType(): string {
		return EventLogger::EVENT_TYPE_RIVER_BASIN_ADD;
	}

	/**
	 * @inheritDoc
	 */
	public static function getDeleteEventType(): string {
		return EventLogger::EVENT_TYPE_RIVER_BASIN_DELETE;
	}

	/**
	 * Delete an item.
	 * Logs the event.
	 *
	 * @param mixed    $id         Item's identifier to delete.
	 * @param bool   $soft       Possible to soft delete an item.
	 * @param string $primaryKey The primary key of the specified table.
	 *
	 * @throws MissingEventTypeException
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\BaseQueries::getDeleteEventType()
	 * @uses \Environet\Sys\General\Db\Query\Delete::run()
	 */
	public static function delete($id, bool $soft = false, string $primaryKey = 'id') {
		EventLogger::log(static::getDeleteEventType(), ['id' => $id]);

		if ($soft) {
			(new Update())
				->table(static::$tableName)
				->where(static::$tableName . '.' . 'id' . ' = :id')
				->addSet('deleted_at', ':deletedAt')
				->setParameters([
					':id'        => $id,
					':deletedAt' => date('Y-m-d H:i:s')
				])
				->run();
		} else {
			(new Delete())->table(static::$tableName)->where('id' . ' = :id')->addParameter(':id', $id)->run();
		}
	}


}
