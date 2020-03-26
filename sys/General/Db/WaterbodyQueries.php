<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\EventLogger;

/**
 * Class WaterbodyQueries
 *
 * @package Environet\Sys\General\Db
 * @author  Mate Kovacs <mate.kovacs@srg.hu>
 */
class WaterbodyQueries extends BaseQueries {

	/**
	 * @inheritdoc
	 */
	public static $tableName = 'waterbody';

	/**
	 * @inheritDoc
	 */
	public static $searchableFields = [
		'waterbody.european_river_code',
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
	public static function getUpdateEventType() : string {
		return EventLogger::EVENT_TYPE_WATERBODY_UPDATE;
	}


	/**
	 * @inheritDoc
	 */
	public static function getInsertEventType() : string {
		return EventLogger::EVENT_TYPE_WATERBODY_ADD;
	}


}
