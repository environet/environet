<?php

namespace Environet\Sys\Admin\Pages\Meteo;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class ResultsCrud
 *
 * Handles CRUD operations for meteopoint measurement results.
 *
 * @package Environet\Sys\Admin\Pages\Meteo
 * @author  SRG Group <dev@srg.hu>
 */
class ResultsCrud extends CrudPage {


	/**
	 * List page action for meteopoint measurement results.
	 *
	 * @return Response
	 * @throws RenderException
	 */
	public function list(): Response {
		try {
			// get search param from query string
			$searchString = $this->request->getQueryParam('search');

			//Base query with joins and conditions
			$query = (new Select())->from('meteo_result mr')
								   ->join('meteo_time_series mts', 'mts.id = mr.meteo_time_seriesid', Query::JOIN_LEFT)
								   ->join('meteopoint mp', 'mp.id = mts.meteopointid', Query::JOIN_LEFT)
								   ->join('meteo_observed_property mop', 'mop.id = mts.meteo_observed_propertyid', Query::JOIN_LEFT)
								   ->orderBy('mr.time', Query::DIR_DESC)
								   ->select([
									   'mp.name',
									   'mop.symbol',
									   'mr.value',
									   'mr.time',
									   'mts.phenomenon_time_begin',
									   'mts.phenomenon_time_end',
									   'mts.result_time',
								   ]);

			if (!is_null($searchString)) {
				$query->search(
					explode(' ', urldecode($searchString)),
					[
						'mp.name',
						'mop.symbol',
					]
				);
			}

			//Add pagination options to query, and get the page info (count, pages)
			$currentPage = $this->request->getQueryParam('page', 1);
			$query->paginate(
				self::PAGE_SIZE,
				$currentPage,
				$totalCount,
				$maxPage
			);

			//Add order by query condition
			$query->clearOrderBy();
			$query->sort(
				$this->request->getQueryParam('order_by'),
				$this->request->getQueryParam('order_dir', 'ASC')
			);

			//Run query
			$results = $query->run();
		} catch (QueryException $exception) {
			$results = [];
		}

		return $this->render('/meteo/results/index.phtml', compact('results', 'totalCount', 'currentPage', 'maxPage', 'searchString'));
	}


}
