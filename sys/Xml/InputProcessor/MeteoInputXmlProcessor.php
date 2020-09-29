<?php

namespace Environet\Sys\Xml\InputProcessor;

use DateTime;
use DateTimeZone;
use Environet\Sys\General\Db\Query\Insert;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\Query\Update;
use Environet\Sys\General\Exceptions\ApiException;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\Upload\Exceptions\UploadException;
use Exception;

/**
 * Class MeteoInputXmlProcessor
 *
 * XML processor class for meteo points
 *
 * @package Environet\Sys\Xml\InputProcessor
 * @author  SRG Group <dev@srg.hu>
 */
class MeteoInputXmlProcessor extends AbstractInputXmlProcessor {


	/**
	 * @inheritDoc
	 * @throws ApiException
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 */
	protected function findMonitoringPoint(string $identifier): ?array {
		try {
			// Find metep monitoring point
			$mPoint = (new Select())
				->from('meteopoint')
				->where('eucd_pst = :id')
				->addParameter('id', $identifier)
				->run(Query::FETCH_FIRST);
		} catch (QueryException $e) {
			throw UploadException::serverError();
		}

		return $mPoint ?: null;
	}


	/**
	 * @inheritDoc
	 * @throws ApiException
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 */
	protected function getPropertyIdIfAllowed(int $mPointId, string $propertySymbol): ?int {
		try {
			// Get the property id with an inner join to meteopoint_observed_property, to get only allowed property id
			$propertyId = (new Select())
				->from('meteo_observed_property as property')
				->select('property.id')
				->join(
					'meteopoint_observed_property as point_property',
					'point_property.meteo_observed_propertyid = property.id',
					Query::JOIN_INNER
				)
				->where('property.symbol = :propertySymbol')
				->where('point_property.meteopointid = :pointId')
				->setParameters([
					'propertySymbol' => $propertySymbol,
					'pointId'        => $mPointId,
				])
				->run(Query::FETCH_FIRST);

			// Return the property id
			return $propertyId ? $propertyId['id'] : null;
		} catch (QueryException $e) {
			throw UploadException::serverError();
		}
	}


	/**
	 * @inheritDoc
	 * @throws ApiException
	 * @throws Exception
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 * @uses \Environet\Sys\General\Db\Query\Insert::run()
	 * @uses \Environet\Sys\General\Db\Query\Update::run()
	 */
	protected function getOrCreateTimeSeries(int $mPointId, int $propertyId, DateTime $now): ?int {
		try {
			// Find time series by id
			$timeSeriesId = (new Select())
				->from('meteo_time_series as time_series')
				->select('time_series.id')
				->where('time_series.meteo_observed_propertyid = :propertyId')
				->where('time_series.meteopointid = :pointId')
				->setParameters([
					'propertyId' => $propertyId,
					'pointId'    => $mPointId,
				])
				->run(Query::FETCH_FIRST);

			// Get id from result
			$timeSeriesId = $timeSeriesId ? $timeSeriesId['id'] : null;
			if (!$timeSeriesId) {
				// Time series for property and monitoring point not found, create a new one
				$now = new DateTime('now', (new DateTimeZone('UTC')));
				$timeSeriesId = (new Insert())
					->table('meteo_time_series')
					->columns(['meteo_observed_propertyid', 'meteopointid', 'result_time'])
					->addValueRow([':propertyId', ':mPointId', ':resultTime'])
					->setParameters([
						'propertyId' => $propertyId,
						'mPointId'   => $mPointId,
						'resultTime' => $now->format('c')
					])
					->run();

				return $timeSeriesId ?? null;
			}

			// Update time series result time
			(new Update())
				->table('meteo_time_series')
				->where('meteo_time_series.id = :tsid')
				->updateData([
					'result_time' => $now->format('c')
				])
				->addParameter('tsid', $timeSeriesId)
				->run();

			return $timeSeriesId;
		} catch (QueryException $e) {
			throw UploadException::serverError();
		}
	}


	/**
	 * @inheritDoc
	 */
	protected function createResultInsert(): Insert {
		return (new Insert())->table('meteo_result')->columns(['meteo_time_seriesid', 'time', 'value', 'is_forecast', 'created_at'])
			->ignoreConflict(['meteo_time_seriesid', 'time', 'value', 'is_forecast']);
	}


}