<?php

namespace Environet\Sys\Admin\Pages\Hydro;

use Environet\Sys\Admin\Pages\Results\MonitoringPointResultsCrud;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class ResultsCrud
 *
 * Handles CRUD operations for hydropoint measurement results.
 *
 * @package Environet\Sys\Admin\Pages\Hydro
 * @author  SRG Group <dev@srg.hu>
 */
class ResultsCrud extends MonitoringPointResultsCrud {


	/**
	 * @param bool $plural
	 *
	 * @return string
	 */
	protected function getEntityName(bool $plural = false): string {
		return $plural ? 'hydro results' : 'hydro result';
	}


	/**
	 * @return Select
	 */
	protected function getBaseQuery(): Select {
		return (new Select())->from('hydro_result r')
			->join('hydro_time_series ts', 'ts.id = r.time_seriesid', Query::JOIN_LEFT)
			->join('hydropoint p', 'p.id = ts.mpointid', Query::JOIN_LEFT)
			->join('hydro_observed_property op', 'op.id = ts.observed_propertyid', Query::JOIN_LEFT)
			->orderBy('hr.time', Query::DIR_DESC)
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
				'ts.result_time',
			]);
	}


	/**
	 * @return string
	 */
	protected function getTemplate(): string {
		return '/hydro/results/index.phtml';
	}


	/**
	 * @return array
	 * @throws QueryException
	 */
	protected function getCountries(): array {
		return array_filter((new Select())
			->select('DISTINCT(country)')
			->from('hydropoint')
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
			->from('hydro_observed_property')
			->orderBy('symbol', 'ASC')
		->run());
	}


	/**
	 * @return string[]
	 */
	protected function getSearchFields(): array {
		return ['ncd_wgst'];
	}


}
