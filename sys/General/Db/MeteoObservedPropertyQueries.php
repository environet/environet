<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\EventLogger;

/**
 * Class MeteoObservedPropertyQueries
 *
 * @package Environet\Sys\General\Db
 * @author  Mate Kovacs <mate.kovacs@srg.hu>
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
			'symbol'     => $data['symbol'] ?? null,
			'description' => $data['description'] ?? null,
			'unit' => $data['unit'] ?? null,
			'type' => 0,
		];
	}


	/**
	 * @inheritDoc
	 */
	public static function getUpdateEventType() : string {
		return EventLogger::EVENT_TYPE_METEO_OP_UPDATE;
	}


	/**
	 * @inheritDoc
	 */
	public static function getInsertEventType() : string {
		return EventLogger::EVENT_TYPE_METEO_OP_ADD;
	}


}
