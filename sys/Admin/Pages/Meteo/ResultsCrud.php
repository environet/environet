<?php

namespace Environet\Sys\Admin\Pages\Meteo;

use Environet\Sys\Admin\Pages\Results\MonitoringPointResultsCrud;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class ResultsCrud
 *
 * Handles CRUD operations for meteopoint measurement results.
 *
 * @package Environet\Sys\Admin\Pages\Meteo
 * @author  SRG Group <dev@srg.hu>
 */
class ResultsCrud extends MonitoringPointResultsCrud {


	/**
	 * @param bool $plural
	 *
	 * @return string
	 */
	protected function getEntityName(bool $plural = false): string {
		return $plural ? 'meteo results' : 'meteo result';
	}


	/**
	 * @return Select
	 */
	protected function getBaseQuery(): Select {
		return (new Select())->from('meteo_result r')
			->join('meteo_time_series ts', 'ts.id = r.time_seriesid', Query::JOIN_LEFT)
			->join('meteopoint p', 'p.id = ts.mpointid', Query::JOIN_LEFT)
			->join('meteo_observed_property op', 'op.id = ts.observed_propertyid', Query::JOIN_LEFT)
			->orderBy('mr.time', Query::DIR_DESC)
			->select([
				'p.name',
				'op.symbol',
				'r.value',
				'r.time',
				'r.is_forecast',
				'r.is_obsolete',
				'r.created_at',
				'ts.phenomenon_time_begin',
				'ts.phenomenon_time_end',
				'ts.result_time'
			]);
	}


	/**
	 * @return string
	 */
	protected function getTemplate(): string {
		return '/meteo/results/index.phtml';
	}


	/**
	 * @return array
	 * @throws QueryException
	 */
	protected function getCountries(): array {
		return array_filter((new Select())
			->select('DISTINCT(country)')
			->from('meteopoint')
			->orderBy('country', 'ASC')
		->run(Query::FETCH_COLUMN));
	}


	/**
	 * @return array
	 * @throws QueryException
	 */
	protected function getObservedProperties(): array {
		return array_filter((new Select())
			->select(['id', 'symbol as label'])
			->from('meteo_observed_property')
			->orderBy('symbol', 'ASC')
		->run());
	}


	/**
	 * @return string[]
	 */
	protected function getSearchFields(): array {
		return ['ncd_pst'];
	}


}
