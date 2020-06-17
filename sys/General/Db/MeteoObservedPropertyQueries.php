<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\EventLogger;

/**
 * Class MeteoObservedPropertyQueries
 *
 * Base queries adjusted for meteopoint observed properties
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class MeteoObservedPropertyQueries extends BaseQueries {

	/**
	 * @inheritdoc
	 */
	public static $tableName = 'meteo_observed_property';

	/**
	 * @inheritDoc
	 */
	public static $searchableFields = [
		'meteo_observed_property.symbol',
		'meteo_observed_property.description'
	];


	/**
	 * @inheritDoc
	 */
	public static function prepareData(array $data): array {
		return [
			'symbol'      => $data['symbol'] ?? null,
			'description' => $data['description'] ?? null,
			'unit'        => $data['unit'] ?? null,
			'type'        => $data['type'] ?? PROPERTY_TYPE_REALTIME,
		];
	}


	/**
	 * @inheritDoc
	 */
	public static function getUpdateEventType(): string {
		return EventLogger::EVENT_TYPE_METEO_OP_UPDATE;
	}


	/**
	 * @inheritDoc
	 */
	public static function getInsertEventType(): string {
		return EventLogger::EVENT_TYPE_METEO_OP_ADD;
	}
	
	/**
	 * @inheritDoc
	 */
	public static function getDeleteEventType(): string {
		return EventLogger::EVENT_TYPE_METEO_OP_DELETE;
	}


}
