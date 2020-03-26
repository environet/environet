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
 * @package Environet\Sys\General\Db
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
	 * @inheritDoc
	 * @throws QueryException
	 */
	public static function getById($id, string $primaryKey = 'id'): ?array {
		$hydroPoint = parent::getById($id);

		if ($hydroPoint) {
			$hydroPoint['classification'] = HydroStationClassificationQueries::getById($hydroPoint['station_classificationid']);
			$hydroPoint['operator'] = OperatorQueries::getById($hydroPoint['operatorid']);
			$hydroPoint['riverbank'] = RiverbankQueries::getById($hydroPoint['bankid']);
			$hydroPoint['waterbodyeuropean_river_code'] = WaterbodyQueries::getById($hydroPoint['waterbodyeuropean_river_code'], 'european_river_code');
			$hydroPoint['observedProperties'] = (new Select())->select('observed_propertyid')->from('hydropoint_observed_property')
													->where('mpointid = :mpointId')
													->addParameter(':mpointId', $id)
													->run(Query::FETCH_COLUMN);
			$hydroPoint['showObservedProperty'] = (new Select())->from('hydro_observed_property hop')
													->select(['hop.id', 'hop.symbol'])
													->join('hydropoint_observed_property hpop', 'hpop.observed_propertyid = hop.id', Query::JOIN_LEFT)
													->where('hpop.mpointid = :hpopId')
													->addParameter(':hpopId', $id)
													->run();
		}


		return $hydroPoint;
	}


	/**
	 * @inheritDoc
	 */
	public static function prepareData(array $data): array {
		return [
			'name' => $data['name'] ?? null,
			'eucd_wgst' => $data['eucd_wgst'] ?? null,
			'ncd_wgst' => $data['ncd_wgst'] ?? null,
			'lat' => $data['lat'] ?? null,
			'long' => $data['long'] ?? null,
			'z' => $data['z'] ?? null,
			'maplat' => $data['lat'] ?? null,
			'maplong' => $data['long'] ?? null,
			'river_kilometer' => $data['river_kilometer'] ?? null,
			'catchment_area' => $data['catchment_area'] ?? null,
			'river_basin' => $data['river_basin'] ?? null,
			'gauge_zero' => $data['gauge_zero'] ?? null,
			'start_time' => '1999-09-09',
			'end_time' => '2060-09-09',
			'utc_offset' => 0,
			'station_classificationid' => (int) $data['classification'] ?? null,
			'operatorid' => (int) $data['operator'] ?? null,
			'bankid' => (int) $data['riverbank'] ?? null,
			'waterbodyeuropean_river_code' => $data['waterbodyeuropean_river_code'] ?? null,
			'vertical_reference' => $data['vertical_reference'] ?? null,
			'country' => $data['country'] ?? null,
			'location' => $data['location'] ?? null,
		];
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
			EventLogger::log(EventLogger::EVENT_TYPE_HYDRO_MP_UPDATE, array_merge($dataToSave, [
				'id' => $id
			]));

			(new Update())->table(static::$tableName)
				->updateData($dataToSave)
				->where(static::$tableName . '.'.$primaryKey.' = :id')->addParameter(':id', $id)->run(Query::RETURN_BOOL);
		} else {
			$id = (new Insert())->table(static::$tableName)
				->addSingleData($dataToSave)->run();

			EventLogger::log(EventLogger::EVENT_TYPE_HYDRO_MP_ADD, array_merge($dataToSave, [
				'id' => $id
			]));
		}

		self::saveHydropointObservedProperty($data['observedProperties'], $id);
	}


	/**
	 * Save hydropoint relation.
	 *
	 * @param $values
	 * @param $idRight
	 *
	 * @throws QueryException
	 */
	public static function saveHydropointObservedProperty($values, $idRight) {
		//Get user ids'f from posted data, filter it, and filter out duplicates
		if (!empty($values)) {
			// get all assigned observed property to the current monitoring point
			$assignedProperties = (new Select())->select('observed_propertyid')->from('hydropoint_observed_property')
									->where('mpointid = :mpointId')
									->addParameter(':mpointId', $idRight)
									->run(Query::FETCH_COLUMN);

			//Create insert query for new connections
			$insert = (new Insert())->table('hydropoint_observed_property')->columns(['observed_propertyid', 'mpointid']);
			// add default values
			$insert->addParameter(':rightId', $idRight);

			//Add values for all ids
			$skippedRows = 0;
			foreach ($values as $key => $id) {
				// if id is empty = no option selected in the dropdown
				// and the dropdown "key" / identifier has already assigned to the current monitoring point
				// we have to delete
				if (empty($id) && in_array((int) $key, $assignedProperties)) {
					(new Delete())->table('hydropoint_observed_property')
						->where('mpointid = :mpointId')
						->where('observed_propertyid = :observedPropertyId')
						->addParameter(':mpointId', $idRight)
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
