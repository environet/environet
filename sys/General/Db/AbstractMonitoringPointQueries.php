<?php


namespace Environet\Sys\General\Db;


use Environet\Sys\General\Db\Query\Query;
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


}