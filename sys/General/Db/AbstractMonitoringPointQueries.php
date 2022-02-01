<?php


namespace Environet\Sys\General\Db;


use DateTime;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\Query\Update;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class AbstractMonitoringPointQueries
 *
 * Common functionalities for Monitorin point queries
 *
 * @package Environet\Sys\General\Db
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
abstract class AbstractMonitoringPointQueries extends BaseQueries {


	/**
	 * Get type (hydro / meteo)
	 * @return string
	 */
	abstract protected static function getType(): string;


	/**
	 * Update *_time_series tables with result_time value based on result table created_at
	 *
	 * @param int $timeSeriesId
	 *
	 * @throws QueryException
	 */
	public static function updateTimeSeriesResultTime(int $timeSeriesId) {
		$type = static::getType();

		$sql = "
			UPDATE {$type}_time_series ts
			SET result_time = (SELECT MAX(r.created_at) FROM {$type}_result r WHERE r.time_seriesid = :tsid)
			WHERE ts.id = :tsid
		";
		(new Query())->table("{$type}_time_series")->setRawQuery($sql)->addParameter(':tsid', $timeSeriesId)->run();
	}


	/**
	 * Update point_observed_property tables with min/max values and times, for a single time series.
	 *
	 * @param int $timeSeriesId
	 *
	 * @throws QueryException
	 */
	public static function updateTimeSeriesPropertyMinMax(int $timeSeriesId) {
		$type = static::getType();
		$pointPropertyTable = "{$type}point_observed_property";

		//Base sql with variables
		$sql = "
			UPDATE $pointPropertyTable op
			SET {{minMax}}_value = result.value, {{minMax}}_value_time = result.time
			FROM (
				SELECT value, time, {$type}_time_series.id AS tsid, {$type}_time_series.mpointid AS mpid, {$type}_time_series.observed_propertyid AS opid
				FROM {$type}_result AS r
				INNER JOIN {$type}_time_series ON {$type}_time_series.id = r.time_seriesid
				WHERE {$type}_time_series.id = :tsid
				ORDER BY value {{order}}
				LIMIT 1
		    ) result
			WHERE op.mpointid = result.mpid AND op.observed_propertyid = result.opid
		";

		//Replace, and run
		$sqlMin = str_replace('{{order}}', 'ASC', $sql);
		$sqlMin = str_replace('{{minMax}}', 'min', $sqlMin);
		(new Query())->table($pointPropertyTable)->setRawQuery($sqlMin)->addParameter(':tsid', $timeSeriesId)->run();
		$sqlMax = str_replace('{{order}}', 'DESC', $sql);
		$sqlMax = str_replace('{{minMax}}', 'max', $sqlMax);
		(new Query())->table($pointPropertyTable)->setRawQuery($sqlMax)->addParameter(':tsid', $timeSeriesId)->run();
	}


	/**
	 * Update time_series tables with phenomnon begin/end values and times, for a single time series.
	 *
	 * @param int $timeSeriesId
	 *
	 * @throws QueryException
	 */
	public static function updateTimeSeriesPropertyPhenomenon(int $timeSeriesId) {
		$type = static::getType();
		$tsTable = "{$type}_time_series";

		$type = static::getType();

		$sqlBegin = "
			UPDATE $tsTable
			SET phenomenon_time_begin = (SELECT MIN(time) FROM {$type}_result WHERE time_seriesid = :tsid)
			WHERE $tsTable.id = :tsid";
		(new Query())->table($tsTable)->setRawQuery($sqlBegin)->addParameter(':tsid', $timeSeriesId)->run();

		$sqlEnd = "
			UPDATE $tsTable
			SET phenomenon_time_end = (SELECT MAX(time) FROM {$type}_result WHERE time_seriesid = :tsid)
			WHERE $tsTable.id = :tsid";
		(new Query())->table($tsTable)->setRawQuery($sqlEnd)->addParameter(':tsid', $timeSeriesId)->run();
	}


	/**
	 * Update point_observed_property tables last_update value.
	 *
	 * @param int      $mpointId
	 * @param int      $propertyId
	 * @param DateTime $now
	 *
	 * @throws QueryException
	 */
	public static function updatePropertyLastUpdate(int $mpointId, int $propertyId, DateTime $now) {
		$type = static::getType();
		$pointPropertyTable = "{$type}point_observed_property";

		(new Update())
			->table($pointPropertyTable)
			->where($pointPropertyTable . '.observed_propertyid = :opid')
			->where($pointPropertyTable . '.mpointid = :mpid')
			->updateData([
				'last_update' => $now->format('c')
			])
			->addParameter('opid', $propertyId)
			->addParameter('mpid', $mpointId)
			->run();
	}


	/**
	 * Get a point by NCD code, and operator
	 *
	 * @param $ncdField
	 * @param $ncdId
	 * @param $operatorId
	 *
	 * @return array|bool|int|null
	 */
	public static function getByNcdAndOperator($ncdField, $ncdId, $operatorId) {
		try {
			return (new Select())
				->select(static::$tableName . '.*')
				->from(static::$tableName)
				->where(static::$tableName . '.' . $ncdField . ' = :ncdId')
				->where(static::$tableName . '.operatorid = :operatorId')
				->addParameter(':ncdId', $ncdId)
				->addParameter(':operatorId', $operatorId)
				->run(Query::FETCH_FIRST);
		} catch (QueryException $exception) {
			return null;
		}
	}


}