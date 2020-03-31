<?php

namespace Environet\Sys\Admin\Pages\Hydro;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class ResultsCrud
 *
 * Handles CRUD operations for hydropoint measurement results.
 *
 * @package Environet\Sys\Admin\Pages\Hydro
 * @author  SRG Group <dev@srg.hu>
 */
class ResultsCrud extends CrudPage {


	/**
	 * List page action for hydropoint measurement results.
	 *
	 * @return Response
	 * @throws RenderException
	 */
	public function list(): Response {
		try {
			// get search param from query string
			$searchString = $this->request->getQueryParam('search');

			//Base query with joins and conditions
			$query = (new Select())->from('hydro_result hr')
			                       ->join('hydro_time_series hts', 'hts.id = hr.time_seriesid', Query::JOIN_LEFT)
			                       ->join('hydropoint hp', 'hp.id = hts.mpointid', Query::JOIN_LEFT)
			                       ->join('hydro_observed_property hop', 'hop.id = hts.observed_propertyid', Query::JOIN_LEFT)
			                       ->orderBy('hr.time', Query::DIR_DESC)
			                       ->select([
				                       'hp.name',
				                       'hop.symbol',
				                       'hr.value',
				                       'hr.time',
				                       'hts.phenomenon_time_begin',
				                       'hts.phenomenon_time_end',
				                       'hts.result_time',
			                       ]);

			if (!is_null($searchString)) {
				$query->search(
					explode(' ', urldecode($searchString)),
					[
						'hp.name',
						'hop.symbol',
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

		return $this->render('/hydro/results/index.phtml', compact('results', 'totalCount', 'currentPage', 'maxPage', 'searchString'));
	}


}
