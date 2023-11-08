<?php


namespace Environet\Sys\General\Db\Selectors;

use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;
use Exception;

/**
 * Class MonitoringPointSelector
 *
 * Selector class for resolving monitoring point ruleset constraints.
 *
 * @package Environet\Sys\General\Db\Selectors
 * @author  SRG Group <dev@srg.hu>
 */
class MonitoringPointSelector extends BaseAccessSelector {

	/**
	 * @var array
	 */
	private $eucd;


	/**
	 * MonitoringPointSelector constructor.
	 *
	 * @param string $values
	 * @param int    $operatorId
	 * @param int    $type
	 *
	 * @throws QueryException
	 */
	public function __construct(string $values, $type, int $operatorId, ?array $countries = null) {
		$this->eucd = null;
		parent::__construct($values, $type, $operatorId, $countries);
	}


	/**
	 * Get EUCD values of the selected monitoring points
	 *
	 * @return array|string
	 * @throws QueryException
	 */
	public function getEUCD(): array {
		if ($this->eucd === null) {
			if (empty($this->values)) {
				$points = [];
			} elseif ($this->type === MPOINT_TYPE_HYDRO) {
				$points = (new Select())
					->select('id, eucd_wgst as eucd')
					->from('hydropoint')
					->whereIn('id', $this->values, 'eucdParam')
					->run();
			} else {
				$points = (new Select())
					->select('id, eucd_pst as eucd')
					->from('meteopoint')
					->whereIn('id', $this->values, 'eucdParam')
					->run();
			}

			$this->eucd = !empty($points) ? array_column($points, 'eucd') : [];
		}

		return $this->eucd;
	}


	/**
	 * @return string
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Select
	 */
	protected function getHydroPointsByOperator(): string {
		if ($this->isOperatorAdmin()) {
			$select = (new Select())->select('string_agg(hydropoint.id::text, \',\') as points')->from('hydropoint');
			if ($this->countries) {
				$select->whereIn('hydropoint.country', $this->countries, 'countries');
			}
			$points = $select->run(Query::FETCH_FIRST);
		} else {
			$select = (new Select())
				->select('string_agg(hydropoint.id::text, \',\') as points')
				->from('hydropoint')
				->where("hydropoint.operatorid = {$this->operatorId}");
			if ($this->countries) {
				$select->whereIn('hydropoint.country', $this->countries, 'countries');
			}
			$points = $select->run(Query::FETCH_FIRST);
		}

		return $points ? $points['points'] ?? '' : '';
	}


	/**
	 * @return string
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Select
	 */
	protected function getMeteoPointsByOperator(): string {
		if ($this->isOperatorAdmin()) {
			$select = (new Select())->select('string_agg(meteopoint.id::text, \',\') as points')->from('meteopoint');
			if ($this->countries) {
				$select->whereIn('meteopoint.country', $this->countries, 'countries');
			}
			$points = $select->run(Query::FETCH_FIRST);
		} else {
			$select = (new Select())
				->select('string_agg(meteopoint.id::text, \',\') as points')
				->from('meteopoint')
				->where("meteopoint.operatorid = {$this->operatorId}");
			if ($this->countries) {
				$select->whereIn('meteopoint.country', $this->countries, 'countries');
			}
			$points = $select->run(Query::FETCH_FIRST);
		}

		return $points ? $points['points'] ?? '' : '';
	}


	/**
	 * @param       $type
	 * @param array $eucdValues
	 * @param array $availableValues
	 * @param array $countries
	 *
	 * @return array
	 * @throws QueryException
	 */
	public static function checkAgainstEUCD($type, array $eucdValues, array $availableValues, array $countries): array {
		if ($type === MPOINT_TYPE_HYDRO) {
			$select = (new Select())
				->select('hydropoint.id, hydropoint.eucd_wgst as eucd')
				->from('hydropoint');
			if ($eucdValues) {
				$select->whereIn('eucd_wgst', $eucdValues, 'eucdParam');
			}
			if ($countries) {
				$select->whereIn('hydropoint.country', $countries, 'countries');
			}
			$requestedPoints = $select->run();
		} else {
			$select = (new Select())
				->select('meteopoint.id, meteopoint.eucd_pst as eucd')
				->from('meteopoint');
			if ($eucdValues) {
				$select->whereIn('eucd_pst', $eucdValues, 'eucdParam');
			}
			if ($countries) {
				$select->whereIn('meteopoint.country', $countries, 'countries');
			}
			$requestedPoints = $select->run();
		}

		if (!empty($eucdValues) && count($eucdValues) !== count($requestedPoints)) {
			$invalid = array_diff($eucdValues, array_column($requestedPoints, 'eucd'));
			if (count($invalid) > 1) {
				throw new Exception('Requested monitoring points are invalid: ' . implode(', ', $invalid));
			}
			throw new Exception("Requested monitoring point is invalid: " . implode('', $invalid));
		}

		$result = [];
		$unauthorized = array_diff(array_column($requestedPoints, 'id'), $availableValues);
		if (!empty($unauthorized)) {
			foreach ($requestedPoints as $point) {
				if (in_array($point['id'], $unauthorized)) {
					$result[] = $point['eucd'];
				}
			}
		}

		return $result;
	}


	/** @inheritDoc
	 * @throws QueryException
	 * @throws Exception
	 */
	public function unserialize($serialized) {
		if ($serialized === '*') {
			if ($this->type === MPOINT_TYPE_HYDRO) {
				$serialized = $this->getHydroPointsByOperator();
			} elseif ($this->type === MPOINT_TYPE_METEO) {
				$serialized = $this->getMeteoPointsByOperator();
			} else {
				throw new Exception('Invalid monitoring point type!');
			}
		}

		parent::unserialize($serialized);
	}


}
