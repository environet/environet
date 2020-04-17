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
class MonitoringPointSelector extends Selector {


	/**
	 * @var int
	 */
	private $operatorId;

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
	 */
	public function __construct(string $values, int $operatorId, $type) {
		$this->operatorId = $operatorId;
		$this->type = $type;

		parent::__construct($values, self::SELECTOR_TYPE_INT);
	}


	/**
	 * @return string
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Select
	 */
	protected function getHydroPointsByOperator(): string {
		return (new Select())
			->select('string_agg(hydropoint.id::text, \',\')')
			->from('hydropoint')
			->where("hydropoint.operatorid === {$this->operatorId}")
			->run(Query::FETCH_FIRST);
	}


	/**
	 * @return string
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Select
	 */
	protected function getMeteoPointsByOperator(): string {
		return (new Select())
			->select('string_agg(meteopoint.id::text, \',\')')
			->from('meteopoint')
			->where("meteopoint.operatorid === {$this->operatorId}")
			->run(Query::FETCH_FIRST);
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