<?php

namespace Environet\Sys\Admin\Pages\Results;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class MonitoringPointResultsCrud
 *
 * Handles CRUD operations for monitoring point measurement results.
 *
 * @package Environet\Sys\Admin\Pages\Hydro
 * @author  SRG Group <dev@srg.hu>
 */
abstract class MonitoringPointResultsCrud extends CrudPage {


	/**
	 * @return Select
	 */
	abstract protected function getBaseQuery(): Select;


	/**
	 * @return string
	 */
	abstract protected function getTemplate(): string;


	/**
	 * @return array
	 */
	abstract protected function getCountries(): array;


	/**
	 * @return array
	 */
	abstract protected function getObservedProperties(): array;


	/**
	 * Add addition custom fields for search
	 * @return array
	 */
	abstract protected function getSearchFields(): array;


	/**
	 * List page action for hydropoint and meteopoint measurement results.
	 *
	 * @return Response
	 * @throws RenderException
	 */
	public function list(): Response {
		try {
			//Base query with joins and conditions
			$query = $this->getBaseQuery();

			if (($searchString = $this->request->getQueryParam('search'))) {
				// Add addition custom fields for search
				$additionalSearchFields = array_map(function ($field) {
					return "p.$field";
				}, $this->getSearchFields());
				$query->search(
					explode(' ', urldecode($searchString)),
					array_merge(['p.name'], $additionalSearchFields)
				);
			}

			if ($this->request->getQueryParam('country')) {
				$query->where('p.country = :filterCountry')
					->addParameter('filterCountry', $this->request->getQueryParam('country'));
			}
			if ($this->request->getQueryParam('property')) {
				$query->where('op.id = :filterProperty')
					->addParameter('filterProperty', $this->request->getQueryParam('property'));
			}
			if ($this->request->getQueryParam('date_from')) {
				$query->where('DATE(r.time) >= :filterDateFrom')
					->addParameter('filterDateFrom', $this->request->getQueryParam('date_from'));
			}
			if ($this->request->getQueryParam('date_to')) {
				$query->where('DATE(r.time) <= :filterDateTo')
					->addParameter('filterDateTo', $this->request->getQueryParam('date_to'));
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
			$orderByField = $this->request->getQueryParam('order_by', 'r.time');
			$orderByDir = $this->request->getQueryParam('order_dir', 'DESC');
			$query->sort($orderByField, $orderByDir);

			//Run query
			$results = $query->run();
		} catch (QueryException $exception) {
			$orderByField = $orderByDir = null;
			$results = [];
		}

		$precisions = array_map(function ($result) {
			return strlen(substr(rtrim(strrchr($result['value'] ?? 0, "."), '0'), 1));
		}, $results);
		$precision = max(2, $precisions ? max($precisions) : 0);

		$listFilters = $this->getListFilters();
		$pageTitle = $this->getTitle(self::PAGE_LIST);

		return $this->render($this->getTemplate(), compact(
			'results',
			'totalCount',
			'currentPage',
			'maxPage',
			'searchString',
			'listFilters',
			'precision',
			'pageTitle',
			'orderByField',
			'orderByDir'
		));
	}


	/**
	 * @return array[]|null
	 * @throws QueryException
	 */
	protected function getListFilters(): ?array {
		$countries = $this->getCountries();
		$properties = $this->getObservedProperties();

		return [
			'country'   => [
				'label'    => 'Country',
				'options'  => array_combine($countries, $countries),
				'selected' => $this->request->getQueryParam('country') ?? null
			],
			'property'  => [
				'label'    => 'Property',
				'options'  => array_combine(array_column($properties, 'id'), array_column($properties, 'label')),
				'selected' => $this->request->getQueryParam('property') ?? null
			],
			'date_from' => [
				'label'    => 'Date from',
				'type'     => 'date',
				'selected' => $this->request->getQueryParam('date_from') ?? null
			],
			'date_to'   => [
				'label'    => 'Date to',
				'type'     => 'date',
				'selected' => $this->request->getQueryParam('date_to') ?? null
			]
		];
	}


}
