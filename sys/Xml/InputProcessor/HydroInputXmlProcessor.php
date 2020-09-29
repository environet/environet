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
use Environet\Sys\General\Identity;
use Environet\Sys\Upload\Exceptions\UploadException;
use Exception;

/**
 * Class HydroInputXmlProcessor
 *
 * XML processor class for hydro points
 *
 * @package Environet\Sys\Xml\InputProcessor
 * @author  SRG Group <dev@srg.hu>
 */
class HydroInputXmlProcessor extends AbstractInputXmlProcessor {


	/**
	 * @inheritDoc
	 * @throws ApiException
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 */
	protected function findMonitoringPoint(string $identifier, Identity $identity = null): ?array {
		try {
			// Find hydro monitoring point
			$mPointQuery = (new Select())
				->from('hydropoint')
				->where('ncd_wgst = :id')
				->addParameter('id', $identifier);

			if ($identity) {
				$mPointQuery->where('operatorid IN (' . implode(',', $this->getOperatorIdsOfIdentity($identity)) . ')');
			}

			$mPoint = $mPointQuery->run(Query::FETCH_FIRST);
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
			// Get the property id with an inner join to hydropoint_observed_property, to get only allowed property id
			$propertyId = (new Select())
				->from('hydro_observed_property as property')
				->select('property.id')
				->join(
					'hydropoint_observed_property as point_property',
					'point_property.observed_propertyid = property.id',
					Query::JOIN_INNER
				)
				->where('property.symbol = :propertySymbol')
				->where('point_property.mpointid = :pointId')
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
				->from('hydro_time_series as time_series')
				->select('time_series.id')
				->where('time_series.observed_propertyid = :propertyId')
				->where('time_series.mpointid = :pointId')
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
					->table('hydro_time_series')
					->columns(['observed_propertyid', 'mpointid', 'result_time'])
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
				->table('hydro_time_series')
				->where('hydro_time_series.id = :tsid')
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
		return (new Insert())->table('hydro_result')->columns(['time_seriesid', 'time', 'value', 'is_forecast', 'created_at'])
			->ignoreConflict(['time_seriesid', 'time', 'value', 'is_forecast']);
	}


}