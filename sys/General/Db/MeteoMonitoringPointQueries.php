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
 * @package Environet\Sys\General\Db
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
	 * @inheritDoc
	 */
	public static function getById($id, string $primaryKey = 'id'): ?array {
		$monitoringPoint = parent::getById($id);

		if ($monitoringPoint) {
			$monitoringPoint['classification'] = $monitoringPoint['meteostation_classificationid'] ? MeteoStationClassificationQueries::getById($monitoringPoint['meteostation_classificationid']) : null;
			$monitoringPoint['operator'] = $monitoringPoint['operatorid'] ? OperatorQueries::getById($monitoringPoint['operatorid']) : null;
			$monitoringPoint['observedProperties'] = (new Select())->select('meteo_observed_propertyid')->from('meteopoint_observed_property')
														->where('meteopointid = :meteopointId')
														->addParameter(':meteopointId', $id)
														->run(Query::FETCH_COLUMN);

			$monitoringPoint['showObservedProperty'] = (new Select())->from('meteo_observed_property mop')
														->select(['mop.id', 'mop.symbol'])
														->join(
															'meteopoint_observed_property mpop',
															'mpop.meteo_observed_propertyid = mop.id',
															Query::JOIN_LEFT
														)
														->where('mpop.meteopointid = :mpopId')
														->addParameter(':mpopId', $id)
														->run();
		}

		return $monitoringPoint;
	}


	/**
	 * @param array  $data
	 * @param null   $id
	 * @param string $primaryKey
	 *
	 * @throws QueryException
	 */
	public static function save(array $data, $id = null, string $primaryKey = 'id') {
		$dataToSave = static::prepareData($data);

		if ($id) {
			EventLogger::log(EventLogger::EVENT_TYPE_METEO_MP_UPDATE, array_merge($dataToSave, [
				'id' => $id
			]));
			(new Update())->table(static::$tableName)
				->updateData($dataToSave)
				->where(static::$tableName . '.'.$primaryKey.' = :id')->addParameter(':id', $id)->run(Query::RETURN_BOOL);
		} else {
			$id = (new Insert())->table(static::$tableName)
					->addSingleData($dataToSave)->run();

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
			'name' => $data['name'],
			'eucd_pst' => $data['eucd_pst'],
			'ncd_pst' => $data['ncd_pst'],
			'country' => $data['country'],

			'location' => $data['location'] ?? null,
			'river_basin' => $data['river_basin'] ?? null,

			// numbers
			'long' => isset($data['long']) ? (int) $data['long'] : null,
			'lat' => isset($data['lat']) ? (int) $data['lat'] : null,
			'z' => isset($data['z']) ? (int) $data['z'] : null,
			'maplat' => isset($data['maplat']) ? (int) $data['maplat'] : null,
			'maplong' => isset($data['maplong']) ? (int) $data['maplong'] : null,
			'altitude' => isset($data['altitude']) ? (int) $data['altitude'] : null,
			'vertical_reference' => isset($data['vertical_reference']) ? (int) $data['vertical_reference'] : null,


			// foreign keys
			'meteostation_classificationid' => isset($data['classification']) ? $data['classification'] ?: null : null,
			'operatorid' => isset($data['operator']) ? $data['operator'] ?: null : null,


			// hidden
			'start_time' => '1999-09-09',
			'end_time' => '2060-09-09',
			'utc_offset' => 0,

		];
	}
}
