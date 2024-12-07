<?php

namespace Environet\Sys\Xml\InputProcessor;

use DateTime;
use DateTimeZone;
use Environet\Sys\General\Db\MeteoMonitoringPointQueries;
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
	protected function findMonitoringPoint(string $identifier, Identity $identity = null, bool $activeOnly = false): ?array {
		try {
			// Find meteo monitoring point
			$mPointQuery = (new Select())
				->from('meteopoint')
				->where('ncd_pst = :id')
				->addParameter('id', $identifier);

			if ($identity && $identity->getId() !== SYS_USERNAME) {
				$mPointQuery->where('operatorid IN (' . implode(',', $this->getOperatorIdsOfIdentity($identity)) . ')');
			}

			if ($activeOnly) {
				//Active points only
				$mPointQuery->where('is_active = true');
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
			// Get the property id with an inner join to meteopoint_observed_property, to get only allowed property id
			$propertyId = (new Select())
				->from('meteo_observed_property as property')
				->select('property.id')
				->join(
					'meteopoint_observed_property as point_property',
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
		if (isUploadDryRun()) {
			return 0;
		}
		try {
			// Find time series by id
			$timeSeriesId = (new Select())
				->from('meteo_time_series as time_series')
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
				$timeSeriesId = (new Insert())
					->table('meteo_time_series')
					->columns(['observed_propertyid', 'mpointid'])
					->addValueRow([':propertyId', ':mPointId'])
					->setParameters([
						'propertyId' => $propertyId,
						'mPointId'   => $mPointId
					])
					->run();

				return $timeSeriesId ?? null;
			}

			return $timeSeriesId;
		} catch (QueryException $e) {
			throw UploadException::serverError();
		}
	}


	/**
	 * @inheritDoc
	 */
	protected function createResultInsert(): Insert {
		return (new Insert())->table('meteo_result')->columns(['time_seriesid', 'time', 'value', 'is_forecast', 'is_obsolete', 'created_at'])
			->ignoreConflict(['time_seriesid', 'time', 'value', 'is_forecast']);
	}


	/**
	 * @inheritDoc
	 */
	protected function createResultObsoleteUpdate(): Query {
		$table = 'meteo_result';
		$obsoleteUpdateQuery = "
            UPDATE $table
            SET is_obsolete = CASE WHEN value != :value THEN true ELSE false END
            WHERE (time_seriesid = :tsid AND time = :time AND is_forecast = :isForecast)
        ";

		return (new Query())->table($table)->setRawQuery($obsoleteUpdateQuery);
	}


	/**
	 * @return Query
	 */
	protected function createResultStatisticsSelect(): Query {
		return (new Select())->select('*')
			->from('meteo_result')
			->where('time_seriesid = :tsid')
			->where('time = :time')
			->where('is_forecast = :isForecast')
			->orderBy('created_at', 'DESC');
	}


	/**
	 * @return string
	 */
	protected function getPointQueriesClass(): string {
		return MeteoMonitoringPointQueries::class;
	}


}
