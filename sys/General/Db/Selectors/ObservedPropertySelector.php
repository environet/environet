<?php


namespace Environet\Sys\General\Db\Selectors;

use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;
use Exception;

/**
 * Class ObservedPropertySelector
 *
 * Selector class for resolving observed property ruleset constraints.
 *
 * @package Environet\Sys\General\Db\Selectors
 * @author  SRG Group <dev@srg.hu>
 */
class ObservedPropertySelector extends Selector {

	/**
	 * @var int
	 */
	private $operatorId;

	/**
	 * @var int
	 */
	private $type;


	/**
	 * ObservedPropertySelector constructor.
	 *
	 * @param string $values
	 * @param int    $operatorId
	 * @param        $type
	 */
	public function __construct(string $values, $type, int $operatorId = 0) {
		$this->operatorId = $operatorId;
		$this->type = $type;

		parent::__construct($values, self::SELECTOR_TYPE_INT);
	}


	/**
	 * @param       $type
	 * @param array $points
	 *
	 * @return ObservedPropertySelector
	 * @throws QueryException
	 * @throws Exception
	 * @uses \Environet\Sys\General\Db\Query\Select
	 */
	public static function createWithMonitoringPoints($type, array $points): ObservedPropertySelector {
		$ids = '';
		if ($type === MPOINT_TYPE_HYDRO) {
			$ids = (new Select())
				->select('string_agg(hydro_observed_property.id::text, \',\')')
				->from('hydro_observed_property')
				->join('hydropoint_observed_property', 'hydropoint_observed_property.observed_propertyid = hydro_observed_property.id')
				->whereIn('hydropoint_observed_property.mpointid', $points, 'point')
				->run(Query::FETCH_FIRST);
		} elseif ($type === MPOINT_TYPE_METEO) {
			$ids = (new Select())
				->select('string_agg(meteo_observed_property.id::text, \',\')')
				->from('meteo_observed_property')
				->join('meteopoint_observed_property', 'meteopoint_observed_property.observed_propertyid = meteo_observed_property.id')
				->whereIn('meteopoint_observed_property.mpointid', $points, 'point')
				->run(Query::FETCH_FIRST);
		} else {
			throw new Exception('Invalid monitoring point type!');
		}

		return new ObservedPropertySelector($ids, $type);
	}


	/**
	 * @return string
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Select
	 */
	protected function getHydroPropertiesByOperator(): string {
		return (new Select())
			->select('string_agg(hydro_observed_property.id::text, \',\')')
			->from('hydro_observed_property')
			->join('hydropoint_observed_property', 'hydropoint_observed_property.observed_propertyid = hydro_observed_property.id')
			->join('hydropoint', 'hydropoint.id = hydropoint_observed_property.mpointid')
			->where("hydropoint.operatorid === {$this->operatorId}")
			->run(Query::FETCH_FIRST);
	}


	/**
	 * @return string
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Select
	 */
	protected function getMeteoPropertiesByOperator(): string {
		return (new Select())
			->select('string_agg(meteo_observed_property.id::text, \',\')')
			->from('meteo_observed_property')
			->join('meteopoint_observed_property', 'meteopoint_observed_property.observed_propertyid = meteo_observed_property.id')
			->join('meteopoint', 'meteopoint.id = meteopoint_observed_property.mpointid')
			->where("meteopoint.operatorid === {$this->operatorId}")
			->run(Query::FETCH_FIRST);
	}


	/** @inheritDoc
	 * @throws QueryException
	 * @throws Exception
	 */
	public function unserialize($serialized) {
		if ($serialized === '*') {
			if (!$this->operatorId) {
				throw new Exception('Missing operator ID!');
			}

			if ($this->type === MPOINT_TYPE_HYDRO) {
				$serialized = $this->getHydroPropertiesByOperator();
			} elseif ($this->type === MPOINT_TYPE_METEO) {
				$serialized = $this->getMeteoPropertiesByOperator();
			} else {
				throw new Exception('Invalid monitoring point type!');
			}
		}

		parent::unserialize($serialized);
	}
}