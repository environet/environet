<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\EventLogger;
use Environet\Sys\General\Exceptions\QueryException;

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


	/**
	 * @param int $pointId
	 *
	 * @return array
	 * @throws QueryException
	 */
	public static function getSymbolsByPoint(int $pointId): array {
		return (new Select())
			->from('hydro_observed_property')
			->join('hydropoint_observed_property', 'hydropoint_observed_property.observed_propertyid = hydro_observed_property.id')
			->select('hydro_observed_property.symbol')
			->where('hydropoint_observed_property.mpointid = :pointId')
			->addParameter('pointId', $pointId)
			->run(Query::FETCH_COLUMN) ?: [];
	}


	/**
	 * Get list of properties, but only real-time type
	 *
	 * @return array|null
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 * @uses \exception_logger()
	 */
	public static function getRealTimeOptionList(): ?array {
		try {
			$records = (new Select())
				->from(static::$tableName)
				->select([static::$tableName . '.id', static::$tableName . '.symbol'])
				->where(static::$tableName.'.type = :type')
				->addParameter('type', PROPERTY_TYPE_REALTIME)
				->orderBy(static::$tableName . '.id', 'ASC')
				->run();
			$records = array_combine(array_column($records, 'id'), array_column($records, 'symbol'));

			return $records ?: [];
		} catch (QueryException $e) {
			exception_logger($e);

			return [];
		}
	}


}
