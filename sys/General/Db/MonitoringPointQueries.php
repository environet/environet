<?php


namespace Environet\Sys\General\Db;

use DateTime;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class MonitoringPointQueries
 *
 * Query builder singleton for hydro and meteo data serving
 *
 * @package   Environet\Sys\General\Db
 * @author    SRG Group <dev@srg.hu>
 * @copyright 2020 SRG Group Kft.
 */
class MonitoringPointQueries {

	public const TYPE_HYDRO = 'hydro';
	public const TYPE_METEO = 'meteo';

	/**
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
	 * @var Select
	 */
	protected $select;


	/**
	 * MonitoringPointQueries constructor.
	 */
	private function __construct() {
		$this->filters = [];
		$this->select = new Select();
	}


	/**
	 * Get monitoring point query builder instance
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
	 * Reset builder data
	 */
	public function reset() {
		$this->type = null;
		$this->filters = [];
		$this->select = new Select();
	}


	/**
	 * Set measurement point type
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
	 * Set a start time of measurements (inclusive)
	 *
	 * @param DateTime $value
	 *
	 * @return MonitoringPointQueries
	 */
	public function setStartTime(DateTime $value) {
		$this->filterBy('start_time', 'where', ["{type}_result.time >= timestamp '{$value->format('Y-m-d H:i:s.u')}'"]);

		return $this;
	}


	/**
	 * Set an end time of measurements (inclusive)
	 *
	 * @param DateTime $value
	 *
	 * @return MonitoringPointQueries
	 */
	public function setEndTime(DateTime $value) {
		$this->filterBy('end_time', 'where', ["{type}_result.time <= timestamp '{$value->format('Y-m-d H:i:s.u')}'"]);

		return $this;
	}


	/**
	 * Set one or more country code to filter the measurement points by
	 *
	 * @param array $countries
	 *
	 * @return MonitoringPointQueries
	 */
	public function setCountries($countries = []) {
		if (!is_array($countries)) {
			$countries = [$countries];
		}

		if (!empty($countries)) {
			$this->filterBy('country', 'whereIn', ['{type}point.country', $countries, 'country']);
		}

		return $this;
	}


	/**
	 * Set measurement property symbols
	 *
	 * @param array $symbols
	 *
	 * @return MonitoringPointQueries
	 */
	public function setObservedProperties($symbols = []): MonitoringPointQueries {
		if (!is_array($symbols)) {
			$symbols = [$symbols];
		}

		if (!empty($symbols)) {
			$this->filterBy('property', 'whereIn', ['{type}_observed_property.symbol', $symbols, 'symbols']);
		}

		return $this;
	}


	/**
	 * Internal function to store filters with
	 *
	 * @param string $key
	 * @param string $queryMethod
	 * @param array  $queryParams
	 */
	protected function filterBy(string $key, string $queryMethod, array $queryParams): void {
		$filter = new \stdClass();
		$filter->method = $queryMethod;
		$filter->params = $queryParams;
		$this->filters[$key] = $filter;
	}


	/**
	 * Apply previously set filters to the query
	 */
	protected function applyFilters() {
		foreach ($this->filters as $filter) {
			foreach ($filter->params as $key => $param) {
				$filter->params[$key] = str_replace('{type}', $this->type, $param);
			}
			$this->select->{$filter->method}(...$filter->params);
		}
	}


	/**
	 * Compile and execute the query
	 *
	 * @return array|int
	 * @throws QueryException
	 */
	public function getResults() {
		if ($this->type === null) {
			throw new QueryException('Missing measurement point type!');
		}

		//Sub-select for getting latest value by created at.
		//There can be multiple values per 'time', for outputs we use the latest.
		$subSelect = (new Select())
			->from("{$this->type}_result as result_sub")
			->select("result_sub.value")
			->where("result_sub.time = {$this->type}_result.time")
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

		//Group-by fields for nearly all columns
		$groupBys = [
			"{$this->type}point.id",
			"{$this->type}_observed_property.id",
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
			"{$this->type}_time_series.result_time"
		];

		if ($this->type === self::TYPE_HYDRO) {
			$selectFields = array_merge($selectFields, [
				"{$this->type}point.eucd_wgst",
				"{$this->type}point.ncd_wgst",
			]);
		} elseif ($this->type === self::TYPE_METEO) {
			$selectFields = array_merge($selectFields, [
				"{$this->type}point.eucd_pst",
				"{$this->type}point.ncd_pst",
			]);
		}

		$this->select
			->from("{$this->type}_result")
			->join("{$this->type}_time_series", "{$this->type}_time_series.id = {$this->type}_result.time_seriesid")
			->join("{$this->type}point", "{$this->type}point.id = {$this->type}_time_series.mpointid")
			->join("{$this->type}_observed_property", "{$this->type}_observed_property.id = {$this->type}_time_series.observed_propertyid")
			->where("{$this->type}_result.is_forecast = 'FALSE'")
			->select($selectFields)
			->orderBy('mpoint_id')
			->orderBy('property_id');

		//Add group-bys
		foreach ($groupBys as $groupBy) {
			$this->select->groupBy($groupBy);
		}

		$this->applyFilters();

		return $this->select->run();
	}


}
