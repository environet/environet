<?php

namespace Environet\Sys\Xml\InputProcessor;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Environet\Sys\General\Db\Connection;
use Environet\Sys\General\Db\Query\Insert;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\UserQueries;
use Environet\Sys\General\Exceptions\ApiException;
use Environet\Sys\General\Exceptions\InvalidConfigurationException;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\UniqueConstraintQueryException;
use Environet\Sys\General\Identity;
use Environet\Sys\Upload\Exceptions\UploadException;
use Environet\Sys\Upload\Statistics;
use Exception;
use SimpleXMLElement;

/**
 * Class AbstractInputXmlProcessor
 *
 * A common abstract class for hydro and meteo XML input processors, with a lot of common functionality
 *
 * @package Environet\Sys\Xml\InputProcessor
 * @author  SRG Group <dev@srg.hu>
 */
abstract class AbstractInputXmlProcessor {

	/**
	 * @var SimpleXMLElement The parsed XML
	 */
	private $xml;

	/**
	 * @var Statistics
	 */
	protected $stats;


	/**
	 * InputXmlProcessor constructor.
	 * Sets the internal SimpleXMLElement object.
	 *
	 * @param SimpleXMLElement $xml
	 */
	public function __construct(SimpleXMLElement $xml) {
		$this->xml = $xml;
		$this->stats = new Statistics();
	}


	/**
	 * Find monitoring point in database based on identifier, which is the EUCD identifier
	 *
	 * @param string        $identifier
	 * @param Identity|null $identity
	 * @param bool          $activeOnly
	 *
	 * @return mixed
	 * @throws UploadException
	 */
	abstract protected function findMonitoringPoint(string $identifier, Identity $identity = null, bool $activeOnly = false): ?array;


	/**
	 * Get the property id based on the symbol. The id will be return only if the property is "allowed" for the monitoring point
	 *
	 * @param int    $mPointId
	 * @param string $propertySymbol
	 *
	 * @return int|null
	 * @throws UploadException
	 */
	abstract protected function getPropertyIdIfAllowed(int $mPointId, string $propertySymbol): ?int;


	/**
	 * Get the time series for the property. If not exist, create a new one.
	 * After it it should set the result_time for the old/new time series
	 *
	 * @param int      $mPointId   Monitoring point id
	 * @param int      $propertyId Observed property id
	 * @param DateTime $now        This parameter will be the result_time
	 *
	 * @return int|null
	 * @throws UploadException
	 */
	abstract protected function getOrCreateTimeSeries(int $mPointId, int $propertyId, DateTime $now): ?int;


	/**
	 * Create a base insert request for results table
	 *
	 * @return Insert
	 */
	abstract protected function createResultInsert(): Insert;


	/**
	 * Crate a base update request for results table
	 *
	 * @return Query
	 */
	abstract protected function createResultUpdate(): Query;


	/**
	 * @return Query
	 */
	abstract protected function createResultStatisticsSelect(): Query;


	/**
	 * Get class of Queries
	 *
	 * @return string
	 */
	abstract protected function getPointQueriesClass(): string;


	/**
	 * Check if mpoint found under this type (hydro or meteo)
	 *
	 * @param Identity $identity
	 *
	 * @return bool
	 */
	public function isValidType(Identity $identity): bool {
		try {
			// Find monitoring point id in xml
			$monitoringPointId = (string) $this->xml->xpath('/environet:UploadData/environet:MonitoringPointId[1]')[0] ?? null;

			// Find monitoring point in database
			return !!($mPoint = $this->findMonitoringPoint($monitoringPointId, $identity));
		} catch (Exception $e) {
			return false;
		}
	}


	/**
	 * @return Statistics
	 */
	public function getStatistics(): Statistics {
		return $this->stats;
	}


	/**
	 * Process the validated xml, and save the time series data in database
	 * Does the following steps:
	 * 1. Creates an UTC-time from the current timestamp.
	 * 2. Finds the monitoring point id in xml. {@see AbstractInputXmlProcessor::findMonitoringPoint()}
	 * 3. Iterates the observed properties in the xml, and updates time series for them.
	 * 4. Inserts the results in the database. {@see AbstractInputXmlProcessor::insertResults()}
	 *
	 * @param Identity $identity
	 *
	 * @throws ApiException
	 * @throws InvalidConfigurationException
	 * @throws UploadException
	 * @see  Connection
	 * @uses AbstractInputXmlProcessor::findMonitoringPoint
	 * @uses AbstractInputXmlProcessor::getPropertyIdIfAllowed
	 * @uses AbstractInputXmlProcessor::getOrCreateTimeSeries
	 * @uses AbstractInputXmlProcessor::insertResults
	 */
	public function process(Identity $identity) {
		// Create an UTC-time
		$now = new DateTime('now', (new DateTimeZone('UTC')));

		try {
			Connection::getInstance()->pdo->beginTransaction();

			// Find monitoring point id in xml
			$monitoringPointId = (string) $this->xml->xpath('/environet:UploadData/environet:MonitoringPointId[1]')[0] ?? null;

			// Find monitoring point in database
			if (!($mPoint = $this->findMonitoringPoint($monitoringPointId, $identity, true))) {
				$identityData = $identity->getData();
				$messages = [
					'NCD: ' . $monitoringPointId,
				];
				if (!empty($identityData['username'])) {
					$messages[] = 'Username: ' . $identityData['username'];
				}
				if ($this->findMonitoringPoint($monitoringPointId, $identity)) {
					//Found if inactive, add different error message
					throw new UploadException(405, $messages);
				}

				throw new UploadException(402, $messages);
			}

			// Find properties in xml, and update time series for all property
			$properties = $this->xml->xpath('environet:Property');

			//Set count of properties in stats
			$this->stats->setInputPropertiesCount(count($properties));
			foreach ($properties as $property) {
				// Get property's symbol
				$propertySymbol = (string) $property->xpath('environet:PropertyId[1]')[0] ?? null;

				//Add property to stats
				$this->stats->addProperty($propertySymbol);

				$timeSeriesPoints = $property->xpath('environet:TimeSeries/environet:Point');

				//Add count of property values to stats
				$this->stats->setPropertyValuesCount($propertySymbol, count($timeSeriesPoints));

				// Get the id of the property which will be returned only if the point can measure the property
				if (!($propertyId = $this->getPropertyIdIfAllowed($mPoint['id'], $propertySymbol))) {
					$identityData = $identity->getData();
					$messages = [
						'Monitoring point NCD: ' . $monitoringPointId . ", Property symbol: " . $propertySymbol,
					];
					if (!empty($identityData['username'])) {
						$messages[] = 'Username: ' . $identityData['username'];
					}
					throw new UploadException(403, $messages);
				}

				// Get the time series id, or create a new one
				if (is_null($timeSeriesId = $this->getOrCreateTimeSeries($mPoint['id'], $propertyId, $now))) {
					$identityData = $identity->getData();
					$messages = [
						'Monitoring point NCD: ' . $monitoringPointId . ', Property symbol: ' . $propertySymbol,
					];
					if (!empty($identityData['username'])) {
						$messages[] = 'Username: ' . $identityData['username'];
					}
					throw new UploadException(404, $messages);
				}

				// Insert results
				$this->insertResults($timeSeriesPoints, $timeSeriesId, $propertySymbol);
				if (!isUploadDryRun()) {
					$this->getPointQueriesClass()::updatePropertyLastUpdate($mPoint['id'], $propertyId, $now);
				}
			}
			Connection::getInstance()->pdo->commit();
		} catch (UploadException $exception) {
			// Known error
			Connection::getInstance()->pdo->rollBack();
			throw $exception;
		} catch (Exception $exception) {
			// Some other error
			Connection::getInstance()->pdo->rollBack();
			throw UploadException::serverError();
		}
	}


	/**
	 * Insert results for time series.
	 * It will insert one result for each value under time series.
	 *
	 * @param array|SimpleXMLElement[] $timeSeriesPoints array of environet:Point xml elements
	 * @param int                      $timeSeriesId     Id of time series record
	 * @param string                   $propertySymbol
	 *
	 * @throws ApiException
	 * @uses AbstractInputXmlProcessor::createResultInsert
	 * @uses Insert::run
	 * @uses \DateTime
	 * @uses \DateTimeInterface
	 * @uses \DateTimeZone
	 */
	protected function insertResults(array $timeSeriesPoints, int $timeSeriesId, string $propertySymbol) {
		try {
			$timeSeriesPointsBatches = array_chunk($timeSeriesPoints, 3000, true);

			foreach ($timeSeriesPointsBatches as $batch) {
				// Create empty insert query
				$insert = $this->createResultInsert();
				$now = new DateTime('now', new DateTimeZone('UTC'));

				foreach ($batch as $key => $point) {
					// Convert time to UTC
					$time = DateTime::createFromFormat(DateTimeInterface::ISO8601, (string) $point->xpath('environet:PointTime')[0] ?? null);
					$time->setTimezone(new DateTimeZone('UTC'));

					$value = (string) $point->xpath('environet:PointValue')[0] ?? null;

					//Add result to stats
					$statisticsSelect = $this->createResultStatisticsSelect();
					$this->stats->addResult($propertySymbol, $value, $statisticsSelect->addParameters([
						"tsid"       => $timeSeriesId,
						"time"       => $time->format('c'),
						"isForecast" => $now < $time
					])->run());

					if (isUploadDryRun()) {
						//No update if dry run
						continue;
					}

					// Add 'values' row to insert query
					$insert->addValueRow([":tsid$key", ":time$key", ":value$key", ":isForecast$key", ":createdAt$key"]);
					$insert->addParameters([
						"tsid$key"       => $timeSeriesId,
						"time$key"       => $time->format('c'),
						"value$key"      => $value,
						"isForecast$key" => $now < $time,
						"createdAt$key"  => $now->format('c'),
					]);

					$update = $this->createResultUpdate();
					$update->addParameters([
						"tsid"       => $timeSeriesId,
						"time"       => $time->format('c'),
						"isForecast" => $now < $time,
						"value"      => $value,
					]);
					$update->run();
				}

				try {
					if (!isUploadDryRun()) {
						$insert->run();
					}
				} catch (UniqueConstraintQueryException $exception) {
					//Do not add the same results
					continue;
				}
			}

			//Update min-max values of time series
			if (!isUploadDryRun()) {
				$this->getPointQueriesClass()::updateTimeSeriesPropertyMinMax($timeSeriesId);
				$this->getPointQueriesClass()::updateTimeSeriesPropertyPhenomenon($timeSeriesId);
				$this->getPointQueriesClass()::updateTimeSeriesResultTime($timeSeriesId);
			}
		} catch (QueryException $e) {
			throw UploadException::serverError();
		}
	}


	/**
	 * @param Identity $identity
	 *
	 * @return array
	 * @throws QueryException
	 */
	protected function getOperatorIdsOfIdentity(Identity $identity): array {
		$groups = array_column(UserQueries::getUserGroups($identity->getId()), 'id');

		return array_column(UserQueries::getMergedOperatorsOfUser($identity->getId(), $groups ?: []), 'id');
	}


}
