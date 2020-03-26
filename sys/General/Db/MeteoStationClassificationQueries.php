<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\EventLogger;

/**
 * Class MeteoStationClassificationQueries
 *
 * @package Environet\Sys\General\Db
 * @author  Mate Kovacs <mate.kovacs@srg.hu>
 */
class MeteoStationClassificationQueries extends BaseQueries {

	/**
	 * @inheritdoc
	 */
	public static $tableName = 'meteostation_classification';

	/**
	 * @inheritDoc
	 */
	public static $searchableFields = [
		'meteostation_classification.value',
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
		return EventLogger::EVENT_TYPE_METEO_SC_UPDATE;
	}


	/**
	 * @inheritDoc
	 */
	public static function getInsertEventType() : string {
		return EventLogger::EVENT_TYPE_METEO_SC_ADD;
	}


}
