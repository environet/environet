<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\EventLogger;

/**
 * Class RiverbankQueries
 *
 * Base queries adjusted for riverbanks
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class RiverbankQueries extends BaseQueries {

	/**
	 * @inheritDoc
	 */
	public static $tableName = 'riverbank';
	
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
		return EventLogger::EVENT_TYPE_HYDRO_RIVERBANK_UPDATE;
	}
	
	
	/**
	 * @inheritDoc
	 */
	public static function getInsertEventType() : string {
		return EventLogger::EVENT_TYPE_HYDRO_RIVERBANK_ADD;
	}
	
	/**
	 * @inheritDoc
	 */
	public static function getDeleteEventType() : string {
		return EventLogger::EVENT_TYPE_HYDRO_RIVERBANK_DELETE;
	}
	
}
