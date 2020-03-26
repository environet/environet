<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\EventLogger;

/**
 * Class HydroStationClassificationQueries
 *
 * @package Environet\Sys\General\Db
 * @author  Mate Kovacs <mate.kovacs@srg.hu>
 */
class HydroStationClassificationQueries extends BaseQueries {

	/**
	 * @inheritdoc
	 */
	public static $tableName = 'hydrostation_classification';

	/**
	 * @inheritDoc
	 */
	public static $searchableFields = [
		'hydrostation_classification.value',
	];

	/**
	 * @inheritDoc
	 */
	public static function prepareData(array $data): array {
		return [
			'value' => $data['value'] ?? null,
		];
	}


	/**
	 * @inheritDoc
	 */
	public static function getUpdateEventType() : string {
		return EventLogger::EVENT_TYPE_HYDRO_SC_UPDATE;
	}


	/**
	 * @inheritDoc
	 */
	public static function getInsertEventType() : string {
		return EventLogger::EVENT_TYPE_HYDRO_SC_ADD;
	}


}
