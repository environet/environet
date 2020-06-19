<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\Db\Query\Insert;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\Query\Update;
use Environet\Sys\General\EventLogger;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class HydroMonitoringPointQueries
 *
 * Queries for hydro monitoring points.
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class HydroMonitoringPointQueries extends BaseQueries {

	/**
	 * @inheritdoc
	 */
	public static $tableName = 'hydropoint';

	/**
	 * @inheritDoc
	 */
	public static $searchableFields = [
		'hydropoint.waterbodyeuropean_river_code',
		'hydropoint.country',
		'hydropoint.name',
		'hydropoint.location',
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
				->from('hydro_observed_property hop')
				->select('hop.symbol')
				->join('hydropoint_observed_property hpop', 'hpop.observed_propertyid = hop.id', Query::JOIN_LEFT)
				->where('hpop.mpointid = :hpopId')
				->addParameter(':hpopId', $point['id'])
				->run(Query::FETCH_COLUMN);
		}

		return $points;
	}


	/**
	 * @inheritDoc
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\HydroStationClassificationQueries::getById()
	 * @uses \Environet\Sys\General\Db\OperatorQueries::getById()
	 * @uses \Environet\Sys\General\Db\RiverbankQueries::getById()
	 * @uses \Environet\Sys\General\Db\WaterbodyQueries::getById()
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 */
	public static function getById($id, string $primaryKey = 'id'): ?array {
		$monitoringPoint = parent::getById($id);

		if ($monitoringPoint) {
			$monitoringPoint['classification'] = $monitoringPoint['station_classificationid'] ? HydroStationClassificationQueries::getById($monitoringPoint['station_classificationid']) : null;
			$monitoringPoint['operator'] = $monitoringPoint['operatorid'] ? OperatorQueries::getById($monitoringPoint['operatorid']) : null;
			$monitoringPoint['riverbank'] = RiverbankQueries::getById($monitoringPoint['bankid']);
			$monitoringPoint['waterbody'] = WaterbodyQueries::getById(
				$monitoringPoint['waterbodyeuropean_river_code'],
				'european_river_code'
			);
			$monitoringPoint['observedProperties'] = (new Select())
				->select('observed_propertyid')
				->from('hydropoint_observed_property')
				->where('mpointid = :mpointId')
				->addParameter(':mpointId', $id)
				->run(Query::FETCH_COLUMN);
			$monitoringPoint['showObservedProperty'] = (new Select())
				->from('hydro_observed_property hop')
				->select(['hop.id', 'hop.symbol'])
				->join('hydropoint_observed_property hpop', 'hpop.observed_propertyid = hop.id', Query::JOIN_LEFT)
				->where('hpop.mpointid = :hpopId')
				->addParameter(':hpopId', $id)
				->run();
		}


		return $monitoringPoint;
	}


	/**
	 * @inheritDoc
	 */
	public static function prepareData(array $data): array {
		return [
			// strings
			'name'                         => $data['name'],
			'eucd_wgst'                    => $data['eucd_wgst'],
			'ncd_wgst'                     => $data['ncd_wgst'],
			'country'                      => $data['country'],
			'location'                     => $data['location'] ?? null,
			'river_basin'                  => $data['river_basin'] ?? null,
			'vertical_reference'           => $data['vertical_reference'] ?? null,

			// numbers
			'river_kilometer'              => isset($data['river_kilometer']) ? (float) $data['river_kilometer'] : null,
			'catchment_area'               => isset($data['catchment_area']) ? (float) $data['catchment_area'] : null,
			'gauge_zero'                   => isset($data['gauge_zero']) ? (float) $data['gauge_zero'] : null,
			'long'                         => isset($data['long']) ? (float) $data['long'] : null,
			'lat'                          => isset($data['lat']) ? (float) $data['lat'] : null,
			'z'                            => isset($data['z']) ? (float) $data['z'] : null,
			'maplat'                       => isset($data['maplat']) ? (float) $data['maplat'] : null,
			'maplong'                      => isset($data['maplong']) ? (float) $data['maplong'] : null,


			// foreign keys
			'station_classificationid'     => isset($data['classification']) ? $data['classification'] ?: null : null,
			'operatorid'                   => isset($data['operator']) ? $data['operator'] ?: null : null,
			'bankid'                       => isset($data['riverbank']) ? $data['riverbank'] ?: null : null,
			'waterbodyeuropean_river_code' => isset($data['waterbody']) ? $data['waterbody'] ?: null : null,

			// dates
			'start_time'                   => !empty($data['start_time']) ? $data['start_time'] : null,
			'end_time'                     => !empty($data['end_time']) ? $data['end_time'] : null,

			// hidden
			'utc_offset'                   => 0,
		];
	}


	/**
	 * Update or insert an item to the hydropoint table ({@see HydroMonitoringPointQueries::$tableName}).
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
	 * @uses \Environet\Sys\General\Db\BaseQueries::saveConnections()
	 */
	public static function save(array $data, $id = null, string $primaryKey = 'id') {
		$dataToSave = static::prepareData($data);

		if ($id) {
			EventLogger::log(EventLogger::EVENT_TYPE_HYDRO_MP_UPDATE, array_merge($dataToSave, [
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

			EventLogger::log(EventLogger::EVENT_TYPE_HYDRO_MP_ADD, array_merge($dataToSave, [
				'id' => $id
			]));
		}

		// Save observed properties
		static::saveConnections(
			$data['observedProperties'] ?? [],
			'hydropoint_observed_property',
			'observed_propertyid',
			'mpointid',
			$id,
			true
		);
	}
	
	/**
	 * @inheritDoc
	 */
	public static function getDeleteEventType(): string {
		return EventLogger::EVENT_TYPE_HYDRO_MP_DELETE;
	}

}
