<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\Db\Query\Delete;
use Environet\Sys\General\Db\Query\Insert;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\Query\Update;
use Environet\Sys\General\EventLogger;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class MeteoMonitoringPointQueries
 *
 * Queries for meteo monitoring points.
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class MeteoMonitoringPointQueries extends BaseQueries {

	/**
	 * @inheritdoc
	 */
	public static $tableName = 'meteopoint';

	/**
	 * @inheritDoc
	 */
	public static $searchableFields = [
		'meteopoint.country',
		'meteopoint.name',
		'meteopoint.location',
	];


	/**
	 * @param array $operatorIds
	 * @return array
	 * @throws QueryException
	 */
	public static function all(array $operatorIds = null) {
		$query = (new Select())
			->select(static::$tableName. '.*')
			->from(static::$tableName);

		if (!is_null($operatorIds)) {
			$query->whereIn('operatorid', $operatorIds, 'operatorId');
		}

		$points = $query->run();

		foreach ($points as $i => $point) {
			$points[$i]['observed_properties'] = (new Select())
				->from('meteo_observed_property hop')
				->select('hop.symbol')
				->join('meteopoint_observed_property hpop', 'hpop.meteo_observed_propertyid = hop.id', Query::JOIN_LEFT)
				->where('hpop.meteopointid = :hpopId')
				->addParameter(':hpopId', $point['id'])
				->run(Query::FETCH_COLUMN);
		}

		return $points;
	}


	/**
	 * @inheritDoc
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\MeteoStationClassificationQueries::getById()
	 * @uses \Environet\Sys\General\Db\OperatorQueries::getById()
	 */
	public static function getById($id, string $primaryKey = 'id'): ?array {
		$monitoringPoint = parent::getById($id);

		if ($monitoringPoint) {
			$monitoringPoint['classification'] = $monitoringPoint['meteostation_classificationid'] ? MeteoStationClassificationQueries::getById($monitoringPoint['meteostation_classificationid']) : null;
			$monitoringPoint['operator'] = $monitoringPoint['operatorid'] ? OperatorQueries::getById($monitoringPoint['operatorid']) : null;
			$monitoringPoint['observedProperties'] = (new Select())
				->select('meteo_observed_propertyid')
				->from('meteopoint_observed_property')
				->where('meteopointid = :meteopointId')
				->addParameter(':meteopointId', $id)
				->run(Query::FETCH_COLUMN);

			$monitoringPoint['showObservedProperty'] = (new Select())
				->select(['mop.id', 'mop.symbol'])
				->from('meteo_observed_property mop')
				->join('meteopoint_observed_property mpop', 'mpop.meteo_observed_propertyid = mop.id', Query::JOIN_LEFT)
				->where('mpop.meteopointid = :mpopId')
				->addParameter(':mpopId', $id)
				->run();
		}

		return $monitoringPoint;
	}


	/**
	 * Update or insert an item to the meteopoint table ({@see  MeteoMonitoringPointQueries::$tableName}).
	 * Logs the transaction regardless of updating or inserting.
	 * Once done, passes id and observed property data to {@see BaseQueries::saveConnections()}.
	 *
	 * @param array  $data
	 * @param null   $id
	 * @param string $primaryKey
	 *
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Insert::run()
	 * @uses \Environet\Sys\General\Db\Query\Update::run()
	 * @uses \Environet\Sys\General\EventLogger::log()
	 * @uses \Environet\Sys\General\Db\MeteoMonitoringPointQueries::prepareData()
	 * @uses \Environet\Sys\General\Db\BaseQueries::saveConnections()
	 */
	public static function save(array $data, $id = null, string $primaryKey = 'id') {
		$dataToSave = static::prepareData($data);

		if ($id) {
			EventLogger::log(EventLogger::EVENT_TYPE_METEO_MP_UPDATE, array_merge($dataToSave, [
				'id' => $id
			]));
			(new Update())
				->table(static::$tableName)
				->updateData($dataToSave)
				->where(static::$tableName . ".$primaryKey = :id")
				->addParameter(':id', $id)
				->run(Query::RETURN_BOOL);
		} else {
			$id = (new Insert())
				->table(static::$tableName)
				->addSingleData($dataToSave)
				->run();

			EventLogger::log(EventLogger::EVENT_TYPE_METEO_MP_ADD, array_merge($dataToSave, [
				'id' => $id
			]));
		}

		// Save observed properties
		static::saveConnections(
			$data['observedProperties'] ?? [],
			'meteopoint_observed_property',
			'meteo_observed_propertyid',
			'meteopointid',
			$id,
			true
		);
	}


	/**
	 * @inheritDoc
	 */
	public static function prepareData(array $data): array {
		return [
			//strings
			'name'                          => $data['name'],
			'eucd_pst'                      => $data['eucd_pst'],
			'ncd_pst'                       => $data['ncd_pst'],
			'country'                       => $data['country'],
			'location'                      => $data['location'] ?? null,
			'river_basin'                   => $data['river_basin'] ?? null,
			'vertical_reference'            => $data['vertical_reference'] ?? null,

			// numbers
			'long'                          => isset($data['long']) ? (float) $data['long'] : null,
			'lat'                           => isset($data['lat']) ? (float) $data['lat'] : null,
			'z'                             => isset($data['z']) ? (float) $data['z'] : null,
			'maplat'                        => isset($data['maplat']) ? (float) $data['maplat'] : null,
			'maplong'                       => isset($data['maplong']) ? (float) $data['maplong'] : null,
			'altitude'                      => isset($data['altitude']) ? (float) $data['altitude'] : null,

			// foreign keys
			'meteostation_classificationid' => isset($data['classification']) ? $data['classification'] ?: null : null,
			'operatorid'                    => isset($data['operator']) ? $data['operator'] ?: null : null,

			// dates
			'start_time'                   => !empty($data['start_time']) ? $data['start_time'] : null,
			'end_time'                     => !empty($data['end_time']) ? $data['end_time'] : null,

			// hidden
			'utc_offset'                    => 0,

		];
	}
	
	/**
	 * @inheritDoc
	 */
	public static function getDeleteEventType(): string {
		return EventLogger::EVENT_TYPE_METEO_MP_DELETE;
	}
}
