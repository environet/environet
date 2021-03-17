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
		'hydropoint.eucd_riv',
		'hydropoint.country',
		'hydropoint.name',
		'hydropoint.location',
	];


	/**
	 * @param array|null $operatorIds
	 * @param bool       $activeOnly
	 *
	 * @return array
	 * @throws QueryException
	 */
	public static function all(array $operatorIds = null, bool $activeOnly = false) {
		$query = (new Select())
			->select(static::$tableName . '.*')
			->from(static::$tableName);

		if (!is_null($operatorIds)) {
			$query->whereIn('operatorid', $operatorIds, 'operatorId');
		}

		if ($activeOnly) {
			//Find active points only
			$query->where('is_active = true');
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
	 * @uses \Environet\Sys\General\Db\RiverQueries::getById()
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 */
	public static function getById($id, string $primaryKey = 'id'): ?array {
		$monitoringPoint = parent::getById($id);

		if ($monitoringPoint) {
			$monitoringPoint['classification'] = $monitoringPoint['station_classificationid'] ? HydroStationClassificationQueries::getById($monitoringPoint['station_classificationid']) : null;
			$monitoringPoint['operator'] = $monitoringPoint['operatorid'] ? OperatorQueries::getById($monitoringPoint['operatorid']) : null;
			$monitoringPoint['riverbank'] = RiverbankQueries::getById($monitoringPoint['bankid']);
			$monitoringPoint['river'] = RiverQueries::getById(
				$monitoringPoint['eucd_riv'],
				'eucd_riv'
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
		$mapKeys = [
			'station_classificationid'     => 'classification',
			'operatorid'                   => 'operator',
			'bankid'                       => 'riverbank',
			'eucd_riv'    => 'river',
		];
		foreach ($mapKeys as $toKey => $fromKey) {
			if (array_key_exists($fromKey, $data)) {
				$data[$toKey] = $data[$fromKey];
				unset($data[$fromKey]);
			}
		}
		$returnData = [
			// strings
			'name'                         => $data['name'] ?? null,
			'ncd_wgst'                     => $data['ncd_wgst'] ?? null,
			'country'                      => $data['country'] ?? null,
			'location'                     => $data['location'] ?? null,
			'river_basin'                  => $data['river_basin'] ?? null,
			'vertical_reference'           => $data['vertical_reference'] ?? null,

			// numbers
			'river_kilometer'              => isset($data['river_kilometer']) && $data['river_kilometer'] !== '' ? (float) $data['river_kilometer'] : null,
			'catchment_area'               => isset($data['catchment_area']) && $data['catchment_area'] !== '' ? (float) $data['catchment_area'] : null,
			'gauge_zero'                   => isset($data['gauge_zero']) && $data['gauge_zero'] !== '' ? (float) $data['gauge_zero'] : null,
			'long'                         => isset($data['long']) && $data['long'] !== '' ? (float) $data['long'] : null,
			'lat'                          => isset($data['lat']) && $data['lat'] !== '' ? (float) $data['lat'] : null,
			'z'                            => isset($data['z']) && $data['z'] !== '' ? (float) $data['z'] : null,
			'maplat'                       => isset($data['maplat']) && $data['maplat'] !== '' ? (float) $data['maplat'] : null,
			'maplong'                      => isset($data['maplong']) && $data['maplong'] !== '' ? (float) $data['maplong'] : null,
			'utc_offset'                   => isset($data['utc_offset']) && $data['utc_offset'] !== '' ? (int) $data['utc_offset'] : null,

			// foreign keys
			'station_classificationid'     => isset($data['station_classificationid']) ? $data['station_classificationid'] ?: null : null,
			'operatorid'                   => isset($data['operatorid']) ? $data['operatorid'] ?: null : null,
			'bankid'                       => isset($data['bankid']) ? $data['bankid'] ?: null : null,
			'eucd_riv'    => isset($data['eucd_riv']) ? $data['eucd_riv'] ?: null : null,

			// dates
			'start_time'                   => !empty($data['start_time']) ? $data['start_time'] : null,
			'end_time'                     => !empty($data['end_time']) ? $data['end_time'] : null,

			//boolean
			'is_active'                    => isset($data['is_active']) && $data['is_active'] !== '' ? (bool) $data['is_active'] : null,
		];

		//Save only fields which have been provided in data array
		$returnData = array_filter($returnData, function ($key) use ($data) {
			return array_key_exists($key, $data);
		}, ARRAY_FILTER_USE_KEY);

		if (!empty($returnData['ncd_wgst']) && !empty($returnData['country'])) {
			$returnData['eucd_wgst'] = self::generateEUCD($returnData['ncd_wgst'], $returnData['country']);
		}

		return $returnData;
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


	/** @inheritDoc */
	public static function delete(int $id, bool $soft = false, string $primaryKey = 'id') {
		EventLogger::log(static::getDeleteEventType(), ['id' => $id]);

		$timeSeries = array_column((new Select())->select('id')->from('hydro_time_series')->where('mpointid = :id')->addParameter(':id', $id)->run(), 'id');
		if (!empty($timeSeries)) {
			(new Delete())->table('hydro_result')->whereIn('time_seriesid', $timeSeries, 'hydroSeriesIds')->run();
			(new Delete())->table('hydro_time_series')->whereIn('id', $timeSeries, 'hydroSeriesIds')->run();
		}

		(new Delete())->table('hydropoint_observed_property')->where('mpointid = :id')->addParameter(':id', $id)->run();
		(new Delete())->table(static::$tableName)->where($primaryKey . ' = :id')->addParameter(':id', $id)->run();
	}


	/**
	 * @inheritDoc
	 */
	public static function getDeleteEventType(): string {
		return EventLogger::EVENT_TYPE_HYDRO_MP_DELETE;
	}


	/**
	 * @param string $ncd
	 * @param string $country
	 *
	 * @return string
	 */
	public static function generateEUCD(string $ncd, string $country): ?string {
		return $country . $ncd . '_HYDRO';
	}


}
