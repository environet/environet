<?php


namespace Environet\Sys\General\Db;

use DateTime;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;
use Exception;
use stdClass;

/**
 * Class MonitoringPointQueries
 *
 * Query builder singleton for hydro and meteo data serving.
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class MonitoringPointQueries {

	public const TYPE_HYDRO = 'hydro';
	public const TYPE_METEO = 'meteo';

	public const EUCD_POSTFIX_HYDRO = 'wgst';
	public const EUCD_POSTFIX_METEO = 'pst';

	/**
	 * Query builder instance
	 * @var MonitoringPointQueries
	 */
	private static $instance = null;

	/**
	 * Measurement point type (hydro or meteo)
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * Filter metrics
	 *
	 * @var array
	 */
	protected $filters;

	/**
	 * Stored query
	 *
	 * @var Select
	 */
	protected $select;

	/**
	 * @var array
	 */
	private $subsets;


	/**
	 * MonitoringPointQueries constructor.
	 */
	private function __construct() {
		$this->filters = [];
		$this->select = new Select();
		$this->type = null;
		$this->subsets = [];
	}


	/**
	 * Get monitoring point query builder instance.
	 *
	 * @return MonitoringPointQueries
	 */
	public static function getBuilder() {
		if (self::$instance == null) {
			self::$instance = new MonitoringPointQueries();
		}

		return self::$instance;
	}


	/**
	 * Reset builder data.
	 */
	public function reset() {
		$this->filters = [];
		$this->select = new Select();
		$this->type = null;
	}


	/**
	 * Return all filters which should be applied to all subqueries as well.
	 *
	 * @return array
	 */
	protected function getGlobalFilters(): array {
		$globalFilters = [];
		if (isset($this->filters['country'])) {
			$globalFilters['country'] = $this->filters['country'];
		}

		return $globalFilters;
	}


	/**
	 * Set measurement point type.
	 *
	 * @param string $type
	 *
	 * @return MonitoringPointQueries
	 */
	public function setType(string $type): MonitoringPointQueries {
		$this->type = $type;

		return $this;
	}


	/**
	 * Set a start time of measurements (inclusive).
	 *
	 * @param DateTime $value
	 *
	 * @return MonitoringPointQueries
	 * @uses \Environet\Sys\General\Db\MonitoringPointQueries::filterBy()
	 */
	public function setStartTime(DateTime $value) {
		$this->filterBy('start_time', 'where', ["{type}_result.time >= timestamp '{$value->format('Y-m-d H:i:s.u')}'"]);

		return $this;
	}


	/**
	 * Set an end time of measurements (inclusive).
	 *
	 * @param DateTime $value
	 *
	 * @return MonitoringPointQueries
	 * @uses \Environet\Sys\General\Db\MonitoringPointQueries::filterBy()
	 */
	public function setEndTime(DateTime $value) {
		$this->filterBy('end_time', 'where', ["{type}_result.time <= timestamp '{$value->format('Y-m-d H:i:s.u')}'"]);

		return $this;
	}


	/**
	 * Set one or more country code to filter the measurement points by.
	 *
	 * @param array $countries
	 *
	 * @return MonitoringPointQueries
	 * @throws Exception
	 * @uses \Environet\Sys\General\Db\MonitoringPointQueries::filterBy()
	 */
	public function setCountries($countries = []) {
		if (!is_array($countries)) {
			$countries = [$countries];
		}

		if (!empty($countries)) {
			$invalid = [];
			foreach ($countries as $country) {
				if (strlen($country) !== 2) {
					$invalid[] = $param;
				}
			}

			if (!empty($invalid)) {
				throw new Exception('Invalid country parameter(s): "' . implode('", "', $invalid) . '"');
			}

			$this->filterBy('country', 'whereIn', ['{type}point.country', $countries, 'country']);
		}

		return $this;
	}


	/**
	 * Set monitoring points by ID.
	 *
	 * @param array $points
	 *
	 * @return MonitoringPointQueries
	 */
	public function setMonitoringPointsById($points = []): MonitoringPointQueries {
		if (!is_array($points)) {
			$points = [$points];
		}

		if (!empty($points)) {
			$this->filterBy('mpoint', 'whereIn', ['{type}point.id', $points, 'points']);
		}

		return $this;
	}


	/**
	 * Set monitoring points by EUCD identifier
	 *
	 * @param array $points
	 *
	 * @return MonitoringPointQueries
	 */
	public function setMonitoringPointsByEUCD($points = []): MonitoringPointQueries {
		if (!is_array($points)) {
			$points = [$points];
		}

		if (!empty($points)) {
			$this->filterBy('mpoint', 'whereIn', ["{type}point.{eucd}", $points, 'points']);
		}

		return $this;
	}


	/**
	 * Set measurement property symbols by ID.
	 *
	 * @param array $ids
	 *
	 * @return MonitoringPointQueries
	 * @uses \Environet\Sys\General\Db\MonitoringPointQueries::filterBy()
	 */
	public function setObservedPropertiesById($ids = []): MonitoringPointQueries {
		if (!is_array($ids)) {
			$ids = [$ids];
		}

		if (!empty($ids)) {
			$this->filterBy('property', 'whereIn', ['{type}_observed_property.id', $ids, 'props']);
		}

		return $this;
	}


	/**
	 * Set measurement property symbols.
	 *
	 * @param array $symbols
	 *
	 * @return MonitoringPointQueries
	 * @uses \Environet\Sys\General\Db\MonitoringPointQueries::filterBy()
	 */
	public function setObservedPropertiesBySymbol($symbols = []): MonitoringPointQueries {
		if (!is_array($symbols)) {
			$symbols = [$symbols];
		}

		if (!empty($symbols)) {
			$this->filterBy('property', 'whereIn', ['{type}_observed_property.symbol', $symbols, 'props']);
		}

		return $this;
	}


	/**
	 * Create queries with different restrictions to be queried in union.
	 *
	 * @param $sets
	 *
	 * @throws QueryException
	 */
	public function createSubsets($sets) {
		$this->subsets = [];

		foreach ($sets as $key => $set) {
			if (!isset($set['points']) || !isset($set['props']) || !isset($set['start']) || !isset($set['end'])) {
				throw new QueryException('Missing monitoring point subset params!');
			}
			if (!$set['start'] instanceof DateTime || !$set['end'] instanceof DateTime) {
				throw new QueryException('Invalid monitoring point subset interval params!');
			}

			$subset = new stdClass();
			$subset->filters = [];
			$subset->intervalLimited = $set['interval_limited'] ?? false;
			$subset->select = new Select();
			$this->subsets[$key] = $subset;

			$this->filterSubsetBy($key, 'mpoint', 'whereIn', ['{type}point.{eucd}', $set['points'], "sub{$key}points"]);
			$this->filterSubsetBy($key, 'property', 'whereIn', ['{type}_observed_property.symbol', $set['props'], "sub{$key}props"]);
			$this->filterSubsetBy($key, 'start_time', 'where', ["{type}_result.time >= timestamp '{$set['start']->format('Y-m-d H:i:s.u')}'"]);
			$this->filterSubsetBy($key, 'end_time', 'where', ["{type}_result.time <= timestamp '{$set['end']->format('Y-m-d H:i:s.u')}'"]);
		}
	}


	/**
	 * Add a pre-made filter to the main select or one of the subset selects
	 *
	 * @param stdClass $filter
	 * @param string   $key
	 * @param null     $subsetKey
	 */
	protected function addFilter(stdClass $filter, string $key, $subsetKey = null) {
		if ($subsetKey === null) {
			$this->filters[$key] = $filter;
		} else {
			$this->subsets[$subsetKey]->filters[$key] = $filter;
		}
	}


	/**
	 * Internal function to store filters with.
	 *
	 * @param string $key
	 * @param string $queryMethod
	 * @param array  $queryParams
	 */
	protected function filterBy(string $key, string $queryMethod, array $queryParams): void {
		$filter = new stdClass();
		$filter->method = $queryMethod;
		$filter->params = $queryParams;
		$this->filters[$key] = $filter;
	}


	/**
	 * Store a filter for a subset.
	 *
	 * @param int|string $subsetKey
	 * @param string     $key
	 * @param string     $queryMethod
	 * @param array      $queryParams
	 */
	protected function filterSubsetBy($subsetKey, string $key, string $queryMethod, array $queryParams): void {
		$filter = new stdClass();
		$filter->method = $queryMethod;
		$filter->params = $queryParams;
		$this->subsets[$subsetKey]->filters[$key] = $filter;
	}


	/**
	 * Apply previously set filters to the query.
	 *
	 * @param null $subsetKey
	 */
	protected function applyFilters($subsetKey = null) {
		$filters = $subsetKey === null ? $this->filters : $this->subsets[$subsetKey]->filters;
		$eucdReplacement = $this->type === self::TYPE_HYDRO ? self::EUCD_POSTFIX_HYDRO : self::EUCD_POSTFIX_METEO;

		foreach ($filters as $filter) {
			foreach ($filter->params as $key => $param) {
				$filter->params[$key] = str_replace('{eucd}', "eucd_$eucdReplacement", str_replace('{type}', $this->type, $param));
			}
			if ($subsetKey === null) {
				$this->select->{$filter->method}(...$filter->params);
			} else {
				$this->subsets[$subsetKey]->select->{$filter->method}(...$filter->params);
			}
		}
	}


	/**
	 * Build the main or one of the sub queries.
	 *
	 * @param Select      $select
	 * @param bool        $isSubset
	 * @param object|null $subsetConfig
	 *
	 * @return Select
	 */
	protected function buildQuery(Select $select, bool $isSubset = false, ?object $subsetConfig = null): Select {
		// Sub-select for getting latest value by created at.
		// There can be multiple values per 'time', for outputs we use the latest.
		$subSelect = (new Select())
			->from("{$this->type}_result as result_sub")
			->select("result_sub.value")
			->where("result_sub.time = {$this->type}_result.time")
			->where("result_sub.time_seriesid = {$this->type}_result.time_seriesid")
			->where("result_sub.is_forecast = 'FALSE'")
			->orderBy('result_sub.created_at', 'DESC')
			->limit(1);
		$subSelectString = $subSelect->buildQuery();

		$selectFields = [
			"{$this->type}point.id as mpoint_id",
			"{$this->type}_observed_property.id as property_id",
			"{$this->type}_time_series.phenomenon_time_begin",
			"{$this->type}_time_series.phenomenon_time_end",
			"{$this->type}point.lat",
			"{$this->type}point.long",
			"{$this->type}point.name as mpoint_name",
			"{$this->type}point.location as mpoint_location",
			"{$this->type}point.utc_offset as mpoint_utc_offset",
			"{$this->type}_observed_property.symbol as property_symbol",
			"{$this->type}_observed_property.description as property_description",
			"{$this->type}_observed_property.unit as property_unit",
			"{$this->type}_result.time as result_time",
			"($subSelectString) as result_value",
			"{$this->type}_time_series.result_time as time_series_result_time"
		];

		$intervalLimited = ($subsetConfig && isset($subsetConfig->intervalLimited) && $subsetConfig->intervalLimited === true) ? 1 : 0;
		$selectFields[] = "$intervalLimited as interval_limited";

		// Group-by fields for nearly all columns
		$groupBys = [
			"mpoint_id",
			"property_id",
			"{$this->type}_time_series.phenomenon_time_begin",
			"{$this->type}_time_series.phenomenon_time_end",
			"{$this->type}point.lat",
			"{$this->type}point.long",
			"{$this->type}point.name",
			"{$this->type}point.location",
			"{$this->type}point.utc_offset",
			"{$this->type}_observed_property.symbol",
			"{$this->type}_observed_property.description",
			"{$this->type}_observed_property.unit",
			"{$this->type}_result.time",
			"{$this->type}_result.time_seriesid",
			"{$this->type}_time_series.result_time"
		];

		$mPointOrderField = 'mpoint_id';
		if ($this->type === self::TYPE_HYDRO) {
			$selectFields = array_merge($selectFields, [
				"{$this->type}point.eucd_wgst",
				"{$this->type}point.ncd_wgst",
			]);
			$mPointOrderField = "eucd_wgst";
		} elseif ($this->type === self::TYPE_METEO) {
			$selectFields = array_merge($selectFields, [
				"{$this->type}point.eucd_pst",
				"{$this->type}point.ncd_pst",
			]);
			$mPointOrderField = "eucd_pst";
		}

		$select
			->from("{$this->type}_result")
			->join("{$this->type}_time_series", "{$this->type}_time_series.id = {$this->type}_result.time_seriesid")
			->join("{$this->type}point", "{$this->type}point.id = {$this->type}_time_series.mpointid")
			->join("{$this->type}_observed_property", "{$this->type}_observed_property.id = {$this->type}_time_series.observed_propertyid")
			->where("{$this->type}_result.is_forecast = 'FALSE'")
			->select($selectFields);

		if (!$isSubset) {
			$select->orderBy($mPointOrderField)->orderBy('property_symbol')->orderBy('result_time');
		}

		// Add group-bys
		foreach ($groupBys as $groupBy) {
			$select->groupBy($groupBy);
		}

		return $select;
	}


	/**
	 * Compile and execute the query.
	 *
	 * @return array|int
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\MonitoringPointQueries::applyFilters()
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 */
	public function getResults() {
		if ($this->type === null) {
			throw new QueryException('Missing measurement point type!');
		}

		if (!empty($this->subsets)) {
			// Get filters which apply to all subsets
			$globalFilters = $this->getGlobalFilters();
			// Overwrite the main select (since it's empty if subsets are used)
			$main = array_shift($this->subsets);
			$this->select = $main->select;
			$this->filters = array_merge($main->filters, $globalFilters);
			foreach ($this->subsets as $key => &$subset) {
				$subset->select = $this->buildQuery($subset->select, true, $subset);
				foreach ($globalFilters as $filterKey => $globalFilter) {
					$this->addFilter($globalFilter, $filterKey, $key);
				}

				$this->applyFilters($key);
				$this->select->union($subset->select);
			}
		}

		$this->select = $this->buildQuery($this->select, false, $main ?? null);
		$this->applyFilters();

		return $this->select->run();
	}


}
