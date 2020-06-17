<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\EventLogger;

/**
 * Class HydroStationClassificationQueries
 *
 * Base queries adjusted for hydro station classifications
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
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
	
	/**
	 * @inheritDoc
	 */
	public static function getDeleteEventType(): string {
		return EventLogger::EVENT_TYPE_HYDRO_SC_DELETE;
	}
}
