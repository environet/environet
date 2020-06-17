<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\EventLogger;

/**
 * Class HydroObservedPropertyQueries
 *
 * Base queries adjusted for hydropoint observed properties
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class HydroObservedPropertyQueries extends BaseQueries {

	/**
	 * @inheritdoc
	 */
	public static $tableName = 'hydro_observed_property';

	/**
	 * @inheritDoc
	 */
	public static $searchableFields = [
		'hydro_observed_property.symbol',
		'hydro_observed_property.description'
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
		return EventLogger::EVENT_TYPE_HYDRO_OP_UPDATE;
	}


	/**
	 * @inheritDoc
	 */
	public static function getInsertEventType(): string {
		return EventLogger::EVENT_TYPE_HYDRO_OP_ADD;
	}
	
	/**
	 * @inheritDoc
	 */
	public static function getDeleteEventType(): string {
		return EventLogger::EVENT_TYPE_HYDRO_OP_DELETE;
	}


}
