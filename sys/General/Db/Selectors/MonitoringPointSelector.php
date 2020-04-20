<?php


namespace Environet\Sys\General\Db\Selectors;

use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Identity;
use Exception;

/**
 * Class MonitoringPointSelector
 *
 * Selector class for resolving monitoring point ruleset constraints.
 *
 * @package Environet\Sys\General\Db\Selectors
 * @author  SRG Group <dev@srg.hu>
 */
class MonitoringPointSelector extends Selector {


	/**
	 * @var Identity
	 */
	private $operator;

	/**
	 * @var int
	 */
	private $type;


	/**
	 * MonitoringPointSelector constructor.
	 *
	 * @param string $values
	 * @param int    $operatorId
	 * @param int    $type
	 *
	 * @throws QueryException
	 */
	public function __construct(string $values, $type, int $operatorId) {
		$this->operator = $this->getOperatorIdentity($operatorId);
		$this->type = $type;

		parent::__construct($values, self::SELECTOR_TYPE_INT);
	}


	/**
	 * @return string
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Select
	 */
	protected function getHydroPointsByOperator(): string {
		if ($this->isOperatorAdmin()) {
			return (new Select())->select('string_agg(hydropoint.id::text, \',\')')->from('hydropoint')->run(Query::FETCH_FIRST);
		}

		return (new Select())
			->select('string_agg(hydropoint.id::text, \',\')')
			->from('hydropoint')
			->where("hydropoint.operatorid === {$this->operator->getId()}")
			->run(Query::FETCH_FIRST);
	}


	/**
	 * @return string
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Select
	 */
	protected function getMeteoPointsByOperator(): string {
		if ($this->isOperatorAdmin()) {
			return (new Select())->select('string_agg(meteopoint.id::text, \',\')')->from('meteopoint')->run(Query::FETCH_FIRST);
		}

		return (new Select())
			->select('string_agg(meteopoint.id::text, \',\')')
			->from('meteopoint')
			->where("meteopoint.operatorid === {$this->operator->getId()}")
			->run(Query::FETCH_FIRST);
	}


	/**
	 * @param $type
	 * @param $eucdValues
	 * @param $availableValues
	 *
	 * @return array
	 * @throws QueryException
	 */
	public static function checkAgainstEUCD($type, array $eucdValues, array $availableValues): array {
		if ($type === MPOINT_TYPE_HYDRO) {
			$requestedPoints = (new Select())
				->select('hydropoint.id, hydropoint.eucd_wgst as eucd')
				->from('hydropoint')
				->whereIn('eucd_wgst', $eucdValues, 'eucdParam')
				->run();
		} else {
			$requestedPoints = (new Select())
				->select('meteopoint.id, meteopoint.eucd_pst as eucd')
				->from('meteopoint')
				->whereIn('eucd_pst', $eucdValues, 'eucdParam')
				->run();
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