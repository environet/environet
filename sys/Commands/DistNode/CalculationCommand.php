<?php


namespace Environet\Sys\Commands\DistNode;

use DateTime;
use DateTimeZone;
use Environet\Sys\Commands\BaseCommand;
use Environet\Sys\Commands\Console;
use Environet\Sys\General\Db\CalculationConfigQueries;
use Environet\Sys\General\Db\HydroMonitoringPointQueries;
use Environet\Sys\General\Db\HydroObservedPropertyQueries;
use Environet\Sys\General\Db\MeteoMonitoringPointQueries;
use Environet\Sys\General\Db\MeteoObservedPropertyQueries;
use Environet\Sys\General\Db\OperatorQueries;
use Environet\Sys\General\Db\Query\Insert;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\Query\Update;
use Environet\Sys\General\Exceptions\QueryException;
use Exception;
use Throwable;

/**
 * Class CalculationCommand
 *
 * Run calculations
 *
 * @package Environet\Sys\Commands
 * @author  SRG Group <dev@srg.hu>
 */
class CalculationCommand extends BaseCommand {

	protected DateTimeZone $tz;


	/**
	 * @param array $arguments
	 * @param array $options
	 *
	 * @return int
	 */
	public function run($arguments, $options): int {
		$this->console->setDatePrefix();

		$this->tz = new DateTimeZone('UTC');
		$dryRun = !empty($options['dry-run']);
		$configId = !empty($options['config-id']) ? (int) $options['config-id'] : null;

		//Date is now, or a give "simulate" now. Reset time to 00:00, without seconds
		$now = !empty($options['date']) ? createValidDate($options['date'], $this->tz) : new DateTime('now', $this->tz);
		$now->setTime($now->format('H'), $now->format('i'));

		$this->console->writeLine(sprintf("Running calculations for time '%s'", $now->format('Y-m-d H:i')));
		if ($dryRun) {
			$this->console->writeLine("Dry run enabled", Console::COLOR_YELLOW);
		}

		try {
			if ($configId) {
				//Get calculation by ID, even if it's not active
				$calculations = CalculationConfigQueries::getById($configId);
				if (!$calculations) {
					$this->console->writeLine(sprintf("Calculation with id #%d not found", $configId), Console::COLOR_RED);

					return 1;
				}
				$this->console->writeLine(sprintf("Found calculation: '%s'", $calculations['name']));
				$calculations = [$calculations];
			} else {
				//Get calculations in active state
				$calculations = CalculationConfigQueries::getActiveCalculations();
				$this->console->writeLine(sprintf("Found %d active calculations", count($calculations)));
			}

			$calculationsSkip = $calculationsFailed = $calculationsSuccess = 0;
			//Iterate over calculations and run them
			foreach ($calculations as $calculation) {
				if (!(($lastCalculation = $this->checkShouldRun($calculation, $now)))) {
					//Calculation should not run now based on current time
					$calculationsSkip ++;

					continue;
				}

				$this->console->writeLineBreak();

				//Run calculation
				$this->console->writeLine(sprintf("Running calculation: '%s'", $calculation['name']));
				$success = $this->runCalculation($calculation, $lastCalculation, $now, $dryRun);

				if ($success) {
					$calculationsSuccess ++;
					$this->console->writeLine("Calculation ran successfully", Console::COLOR_GREEN);
				} else {
					$calculationsFailed ++;
					$this->console->writeLine("Calculation failed", Console::COLOR_RED);
				}
			}

			$this->console->writeLineBreak();
			$this->console->writeLine(sprintf(
				"Calculations: %d total, %d skipped, %d success, %d failed",
				count($calculations),
				$calculationsSkip,
				$calculationsSuccess,
				$calculationsFailed
			));

			return 0;
		} catch (Throwable $e) {
			$this->console->writeLine(sprintf("Error: %s", $e->getMessage()), Console::COLOR_RED);

			return 1;
		}
	}


	/**
	 * Check if the calculation should run now
	 *
	 * @param array    $calculation
	 * @param DateTime $now
	 *
	 * @return DateTime
	 * @throws Exception
	 */
	protected function checkShouldRun(array $calculation, DateTime $now): ?DateTime {
		$targetInterval = $calculation['target_interval']; //String, one of: 'hourly', 'daily', 'weekly', 'monthly', 'yearly'
		$startTime = $calculation['start_time']; //String, format: H:i

		$lastCalculation = !empty($calculation['last_calculation']) ? new DateTime($calculation['last_calculation'], $this->tz) : new DateTime('2020-01-01', $this->tz); //DateTime|null

		if ($lastCalculation > $now) {
			//If the last calculation is in the future, set it to now, so maybe it will overwrite some results, or make corrections
			$lastCalculation = $now;
		}

		//Check config validity
		if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
			throw new Exception(sprintf('Invalid start time format for calculation %s: %s', $calculation['name'], $calculation['start_time']));
		}
		if (!in_array($targetInterval, CalculationConfigQueries::$intervals)) {
			throw new Exception(sprintf('Invalid target interval for calculation %s: %s', $calculation['name'], $calculation['target_interval']));
		}

		//Set start time to last run date
		$startTime = array_map(fn($n) => (int) $n, explode(':', $startTime));
		$lastCalculation->setTime($startTime[0], $startTime[1]);

		return $lastCalculation;
	}


	/**
	 * Run the calculation
	 *
	 * @param array    $calculation
	 * @param DateTime $lastCalculation
	 * @param DateTime $now
	 * @param bool     $dryRun
	 *
	 * @return bool
	 * @throws QueryException
	 */
	protected function runCalculation(array $calculation, DateTime $lastCalculation, DateTime $now, bool $dryRun = false): bool {
		$targetInterval = $calculation['target_interval']; //String, one of: 'hourly', 'daily', 'weekly', 'monthly', 'yearly'
		$sourceInterval = $calculation['source_interval']; //String, one of: 'hourly', 'daily', 'weekly', 'monthly', 'yearly'

		//Get result count constraint. It validates if there are enough results for the calculation
		$resultCountConstraint = $this->getResultCountConstraint($sourceInterval, $targetInterval);

		//Check method of aggregation
		$method = $calculation['method']; //String, one of: 'sum'
		$methodName = 'method' . ucfirst($method);
		if (!method_exists($this, $methodName)) {
			$this->console->writeLine(sprintf("Method '%s' not implemented", $method), Console::COLOR_RED);

			return false;
		}

		//Build queries class based on the type
		$type = $calculation['mpoint_type']; //String, one of: 'meteo', 'hydro'
		$mPointQueries = $type === 'meteo' ? MeteoMonitoringPointQueries::class : HydroMonitoringPointQueries::class;

		$sourceProperty = $this->findProperty($calculation['source_propertyid'], $type, 'source');
		$targetProperty = $this->findProperty($calculation['target_propertyid'], $type, 'target');
		if (!$sourceProperty || !$targetProperty) {
			return false;
		}

		//Find operator
		$operatorId = $calculation['operatorid'];
		$operator = OperatorQueries::getById($operatorId);
		if (!$operator) {
			$this->console->writeLine(sprintf("Operator with id #%d not found", $operatorId), Console::COLOR_RED);

			return false;
		}
		$this->console->writeLine(sprintf("Operator: #%d - %s", $operator['id'], $operator['name']));

		//Find monitoring points for operator
		$mPoints = $mPointQueries::all([$operator['id']]);
		if (!empty($calculation['mpointid'])) {
			//Filter monitoring points by the one specified in the calculation
			$mPoints = array_filter($mPoints, fn($mPoint) => $mPoint['id'] === $calculation['mpointid']);
		}
		$this->console->writeLine(sprintf("Found %d monitoring points for operator '%s'", count($mPoints), $operator['name']));

		//Iterate over monitoring points and run the calculation
		foreach ($mPoints as $mPoint) {
			$insertValues = [];

			//Find time series for source and target properties
			$sourceTimeSeriesId = $this->getTimeSeries($sourceProperty, $mPoint, $type, 'source');
			$targetTimeSeriesId = $this->getTimeSeries($targetProperty, $mPoint, $type, 'target', true);
			if (!$sourceTimeSeriesId || !$targetTimeSeriesId) {
				continue;
			}

			//Determining the minimum time of the source time series. If it is greater than the last run, the calculation will start from the minimum time
			$minTime = (new Select())->from("{$type}_result")->select("MIN({$type}_result.time) as min")
				->where("{$type}_result.time_seriesid = :timeSeriesId")->setParameters(['timeSeriesId' => $sourceTimeSeriesId])->run(Query::FETCH_FIRST);
			$minTime = $minTime['min'] ? new DateTime($minTime['min']) : null;

			//Do the calculation for each interval. First interval is the last run date
			$intervalStart = $lastCalculation;
			do {
				$intervalStartLoop = $intervalStart; //$intervalStart will be modified in the loop
				$intervalEnd = $this->datePlusInterval($intervalStartLoop, $targetInterval); //Calculate the end of the interval
				$intervalStart = $intervalEnd; //Move to the next interval. It is neccessary here because some 'continue' statements can jump to next iteration
				if ($minTime && $minTime > $intervalStart) {
					//If the minimum time of the source time series is greater than the end of the interval, skip this calculation interval
					continue;
				}

				$this->console->write(sprintf("Interval (%s < x <= %s] ... ", $intervalStartLoop->format('Y-m-d H:i'), $intervalEnd->format('Y-m-d H:i')));

				//Find results from the source time series in the interval
				$results = (new Select())->from("{$type}_result")->select("{$type}_result.value, {$type}_result.time")
					->where("{$type}_result.time_seriesid = :timeSeriesId")
					->where("{$type}_result.time > :intervalStart")
					->where("{$type}_result.time <= :intervalEnd")
					->setParameters([
						'timeSeriesId'  => $sourceTimeSeriesId,
						'intervalStart' => $intervalStartLoop->format('Y-m-d H:i:s'),
						'intervalEnd'   => $intervalEnd->format('Y-m-d H:i:s')
					])
					->orderBy('time')->run();
				if (empty($results)) {
					//No results in the interval, skip this interval calculation
					$this->console->writeLine("No results", Console::COLOR_YELLOW, null, false, false, false);
					continue;
				}

				//Filter out results based on the source interval. Results can contain multiple values than the expected frequency of the source interval
				$filteredResults = $this->filterResultsSourceInterval($results, $sourceInterval);

				//Check if the number of results is enough for the calculation based on constraints
				if (!$this->isMatchResultCountConstraint($resultCountConstraint, $filteredResults)) {
					$this->console->writeLine(sprintf(
						"Not enough results (%d) for interval %s - %s",
						count($filteredResults),
						$intervalStartLoop->format('Y-m-d H:i'),
						$intervalEnd->format('Y-m-d H:i')
					), Console::COLOR_YELLOW, null, false, false, false);

					continue;
				}

				//We have enough results, do the calculation
				$value = $this->$methodName($filteredResults);

				//Add the result to the insert values
				$insertValues[] = ['date' => $intervalEnd, 'value' => $value];
				$this->console->writeLine(sprintf("Value: %s", $value), null, null, false, false, false);

				//A valid result is calculated for this interval, so we can set the last calculation to the end of this interval
				$lastCalculation = $intervalEnd;
			} while ($intervalEnd < $now);

			//Insert every result to the target time series
			$this->insertResults($type, $targetTimeSeriesId, $insertValues, $now, $dryRun);
		}

		$calculation['last_calculation'] = $lastCalculation->format('Y-m-d H:i:s');
		if (!$dryRun) {
			(new Update())->table('calculation_configs')->addSet('last_calculation', ':lastCalculation')->where('id = :id')
				->setParameters(['lastCalculation' => $calculation['last_calculation'], 'id' => $calculation['id']])->run();
		}

		return true;
	}


	/**
	 * Sum all results in the interval
	 *
	 * @param array $results
	 *
	 * @return float|int
	 */
	protected function methodSum(array $results) {
		$results = array_map(fn($result) => (float) $result, $results);

		return array_sum($results);
	}


	/**
	 * Calculate the difference between the last and the first result in the interval
	 *
	 * @param array $results
	 *
	 * @return mixed
	 */
	protected function methodDifference(array $results) {
		$results = array_map(fn($result) => (float) $result, $results);

		if (empty($results)) {
			return null;
		}

		return end($results) - reset($results);
	}


	/**
	 * Insert results to the target time series
	 *
	 * @param string   $type         'meteo' or 'hydro'
	 * @param int      $timeSeriesId Time series ID
	 * @param array    $values       Array of values to insert. Each value is an array with 'date' and 'value' keys
	 * @param DateTime $now          Current date
	 * @param bool     $dryRun       If true, the insert query will not be executed
	 *
	 * @return void
	 * @throws QueryException
	 */
	protected function insertResults(string $type, int $timeSeriesId, array $values, DateTime $now, bool $dryRun = false) {
		$chunks = array_chunk($values, 3000, true);
		$realNow = new DateTime('now', $this->tz);

		foreach ($chunks as $chunk) {
			//Create empty insert query
			$insert = (new Insert())->table("{$type}_result")->columns(['time_seriesid', 'time', 'value', 'is_forecast', 'created_at']);

			foreach ($chunk as $i => $value) {
				['date' => $time, 'value' => $value] = $value;

				// Add 'values' row to insert query
				$insert->addValueRow([":tsid$i", ":time$i", ":value$i", ":isForecast$i", ":createdAt$i"]);
				$insert->addParameters([
					"tsid$i"       => $timeSeriesId,
					"time$i"       => $time->format('c'),
					"value$i"      => $value,
					"isForecast$i" => false,
					"createdAt$i"  => $realNow->format('Y-m-d H:i:s'),
				]);
			}

			if (!$dryRun) {
				$insert->run();
			}
		}

		$queriesClass = $type === 'hydro' ? HydroMonitoringPointQueries::class : MeteoMonitoringPointQueries::class;
		//Update min-max values of time series
		if (!$dryRun) {
			$queriesClass::updateTimeSeriesPropertyMinMax($timeSeriesId);
			$queriesClass::updateTimeSeriesPropertyPhenomenon($timeSeriesId);
			$queriesClass::updateTimeSeriesResultTime($timeSeriesId);
		}
	}


	/**
	 * Calculate the end of the interval based on the start and the target interval. It will be the start of the next interval too.
	 *
	 * @param DateTime $intervalStart
	 * @param string   $targetInterval
	 *
	 * @return DateTime|null
	 * @throws \DateMalformedStringException
	 */
	protected function datePlusInterval(DateTime $intervalStart, string $targetInterval): ?DateTime {
		switch ($targetInterval) {
			case CalculationConfigQueries::INTERVAL_HOUR:
				return (clone $intervalStart)->modify('+1 hour');
			case CalculationConfigQueries::INTERVAL_DAY:
				return (clone $intervalStart)->modify('+1 day');
			case CalculationConfigQueries::INTERVAL_WEEK:
				return (clone $intervalStart)->modify('+1 week');
			case CalculationConfigQueries::INTERVAL_MONTH:
				return (clone $intervalStart)->modify('+1 month');
			case CalculationConfigQueries::INTERVAL_YEAR:
				return (clone $intervalStart)->modify('+1 year');
			default:
				return null;
		}
	}


	/**
	 * Get the constraint for the minimum (or exact) number of results for the calculation
	 *
	 * @param string $sourceInterval
	 * @param string $targetInterval
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function getResultCountConstraint(string $sourceInterval, string $targetInterval): array {
		if ($sourceInterval === CalculationConfigQueries::INTERVAL_HOUR && $targetInterval === CalculationConfigQueries::INTERVAL_DAY) {
			//Hourly to daily - exactly 24 results needed
			return ['equal' => 24];
		}
		throw new Exception(sprintf('No min result count implemented for source interval %s and target interval %s', $sourceInterval, $targetInterval));
	}


	/**
	 * Filter results based on the source interval. Results can contain multiple values than the expected frequency of the source interval
	 * We need to filter out the results to match the expected frequency
	 *
	 * @param array  $results
	 * @param string $sourceInterval
	 *
	 * @return array
	 */
	protected function filterResultsSourceInterval(array $results, string $sourceInterval): array {
		$filteredResults = array_values(array_filter($results, function ($result) use ($sourceInterval) {
			try {
				$resultTime = createValidDate($result['time']);
			} catch (Exception $e) {
				//Not a valid date, skip this result
				return false;
			}
			switch ($sourceInterval) {
				case CalculationConfigQueries::INTERVAL_HOUR:
					return $resultTime->format('i') === '00'; //Only full hours
				case CalculationConfigQueries::INTERVAL_DAY:
					return $resultTime->format('H:i') === '00:00'; //Only midnight
				case CalculationConfigQueries::INTERVAL_WEEK:
					return $resultTime->format('H:i') === '00:00' && $resultTime->format('N') === '1'; //Only Monday midnight
				case CalculationConfigQueries::INTERVAL_MONTH:
					return $resultTime->format('H:i') === '00:00' && $resultTime->format('d') === '01'; //Only first day of the month
				case CalculationConfigQueries::INTERVAL_YEAR:
					return $resultTime->format('H:i') === '00:00' && $resultTime->format('d') === '01' && $resultTime->format('m') === '01'; //Only first day of the year
				default:
					return false;
			}
		}));

		return array_combine(
			array_column($filteredResults, 'time'),
			array_column($filteredResults, 'value')
		);
	}


	/**
	 * Check if the number of results is enough for the calculation based on constraints
	 *
	 * @param array $resultCountConstraint
	 * @param array $results
	 *
	 * @return bool
	 */
	protected function isMatchResultCountConstraint(array $resultCountConstraint, array $results): bool {
		if (isset($resultCountConstraint['equal'])) {
			return count($results) === $resultCountConstraint['equal'];
		}

		return false;
	}


	/**
	 * Get (or create) time series for the property and monitoring point
	 *
	 * @param array  $property
	 * @param array  $mPoint
	 * @param string $type
	 * @param string $propertyType
	 * @param bool   $createIfNotExists
	 *
	 * @return int|null
	 * @throws QueryException
	 */
	protected function getTimeSeries(array $property, array $mPoint, string $type, string $propertyType, bool $createIfNotExists = false): ?int {
		$timeSeriesId = (new Select())->from("{$type}_time_series as time_series")->select('time_series.id')
			->where('time_series.observed_propertyid = :propertyId')
			->where('time_series.mpointid = :pointId')
			->setParameters(['propertyId' => $property['id'], 'pointId' => $mPoint['id']])->run(Query::FETCH_FIRST);
		$hasMPointProperty = (new Select())->from("{$type}point_observed_property as point_property")->select('COUNT(*) as count')
			->where('point_property.observed_propertyid = :propertyId')
			->where('point_property.mpointid = :pointId')
			->setParameters(['propertyId' => $property['id'], 'pointId' => $mPoint['id']])->run(Query::FETCH_COUNT);

		if (empty($timeSeriesId['id'])) {
			if ($createIfNotExists) {
				// Time series for property and monitoring point not found, create a new one
				$createdTsId = (new Insert())->table("{$type}_time_series")
					->columns(['observed_propertyid', 'mpointid'])->addValueRow([':propertyId', ':mPointId'])
					->setParameters(['propertyId' => $property['id'], 'mPointId' => $mPoint['id']])->run();

				if (!$createdTsId) {
					$this->console->writeLine(sprintf(
						"Couldn't create new time series for %s property '%s' and monitoring point '%s'",
						$propertyType,
						$property['symbol'],
						$mPoint['name']
					), Console::COLOR_RED);

					return null;
				}

				if (!$hasMPointProperty) {
					(new Insert())->table("{$type}point_observed_property")
						->columns(['observed_propertyid', 'mpointid'])
						->addValueRow([':propertyId', ':pointId'])
						->setParameters(['propertyId' => $property['id'], 'pointId' => $mPoint['id']])->run();
				}

				$timeSeriesId = $createdTsId;
			} else {
				$this->console->writeLine(sprintf(
					"Time series not found for %s property '%s' and monitoring point '%s'",
					$propertyType,
					$property['symbol'],
					$mPoint['name']
				), Console::COLOR_YELLOW);

				return null;
			}
		} else {
			$timeSeriesId = $timeSeriesId['id'];
		}

		return $timeSeriesId;
	}


	/**
	 * Find property by ID
	 *
	 * @param int    $propertyId
	 * @param string $type
	 * @param string $propertyType
	 *
	 * @return array|null
	 */
	protected function findProperty(int $propertyId, string $type, string $propertyType): ?array {
		$propertyQueries = $type === 'meteo' ? MeteoObservedPropertyQueries::class : HydroObservedPropertyQueries::class;

		$property = $propertyQueries::getById($propertyId);
		if (!$property) {
			$this->console->writeLine(sprintf("%s property with id #%d not found", ucfirst($propertyType), $propertyId), Console::COLOR_RED);

			return null;
		}
		$this->console->writeLine(sprintf("%s property: #%d - %s - %s", ucfirst($propertyType), $propertyId, $property['symbol'], $property['description']));

		return $property;
	}


}
