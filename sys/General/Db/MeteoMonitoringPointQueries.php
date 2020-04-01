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

		self::saveMeteoPointObservedProperty($data['observedProperties'], $id);
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

			'location' => $data['location'],
			'river_basin' => $data['river_basin'],

			// numbers
			'long' => (int) $data['long'],
			'lat' => (int) $data['lat'],
			'z' => (int) $data['z'],
			'maplat' => (int) $data['lat'],
			'maplong' => (int) $data['long'],
			'altitude' => (int) $data['altitude'],
			'vertical_reference' => (int) $data['vertical_reference'],


			// foreign keys
			'meteostation_classificationid' => $data['classification'] ?: null,
			'operatorid' => $data['operator'] ?: null,


			// hidden
			'start_time' => '1999-09-09',
			'end_time' => '2060-09-09',
			'utc_offset' => 0,

		];
	}


	/**
	 * Save meteopoint relation.
	 *
	 * @param $values
	 * @param $idRight
	 *
	 * @throws QueryException
	 */
	public static function saveMeteoPointObservedProperty($values, $idRight) {
		//Get user ids'f from posted data, filter it, and filter out duplicates
		if (!empty($values)) {
			// get all assigned observed property to the current monitoring point
			$assignedProperties = (new Select())->select('meteo_observed_propertyid')->from('meteopoint_observed_property')
									->where('meteopointid = :meteopointId')
									->addParameter(':meteopointId', $idRight)
									->run(Query::FETCH_COLUMN);

			//Create insert query for new connections
			$insert = (new Insert())->table('meteopoint_observed_property')->columns(['meteo_observed_propertyid', 'meteopointid']);
			// add default values
			$insert->addParameter(':rightId', $idRight);

			//Add values for all ids
			$skippedRows = 0;
			foreach ($values as $key => $id) {
				// if id is empty = no option selected in the dropdown
				// and the dropdown "key" / identifier has already assigned to the current monitoring point
				// we have to delete
				if (empty($id) && in_array((int) $key, $assignedProperties)) {
					(new Delete())->table('meteopoint_observed_property')
						->where('meteopointid = :meteopointId')
						->where('meteo_observed_propertyid = :observedPropertyId')
						->addParameter(':meteopointId', $idRight)
						->addParameter(':observedPropertyId', $key)
						->run();
				}
				// if the current "id" is empty, so we want to delete or the current "key" / identifier of the select field
				// has already assigned to the current monitoring point we want to do nothing with the current "option"
				if (empty($id) || in_array((int) $id, $assignedProperties)) {
					$skippedRows++;
					continue;
				}

				$insert->addValueRow([":leftId$key", ":rightId"]);
				$insert->addParameters([":leftId$key" => (int) $id]);
			}

			//Run, but without returning last insert id
			if (count($values) > $skippedRows) {
				$insert->run(Query::RETURN_BOOL);
			}
		}
	}


}
