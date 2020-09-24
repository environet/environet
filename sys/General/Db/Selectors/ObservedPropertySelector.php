<?php


namespace Environet\Sys\General\Db\Selectors;

use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Identity;
use Exception;
use http\Client\Curl\User;

/**
 * Class ObservedPropertySelector
 *
 * Selector class for resolving observed property ruleset constraints.
 *
 * @package Environet\Sys\General\Db\Selectors
 * @author  SRG Group <dev@srg.hu>
 */
class ObservedPropertySelector extends BaseAccessSelector {

	/**
	 * @var array
	 */
	private $symbols;


	/**
	 * ObservedPropertySelector constructor.
	 *
	 * @param string $values
	 * @param int    $operatorId
	 * @param        $type
	 *
	 * @throws QueryException
	 */
	public function __construct(string $values, $type, int $operatorId = 0) {
		$this->symbols = null;

		parent::__construct($values, $type, $operatorId);
	}


	/**
	 * Get the associated symbols of this selector
	 *
	 * @return array
	 * @throws QueryException
	 */
	public function getSymbols(): array {
		if ($this->symbols === null) {
			if ($this->type === MPOINT_TYPE_HYDRO) {
				$symbols = (new Select())
					->select('id, symbol')
					->from('hydro_observed_property')
					->whereIn('id', $this->values, 'values')
					->run();
			} else {
				$symbols = (new Select())
					->select('id, symbol')
					->from('meteo_observed_property')
					->whereIn('id', $this->values, 'values')
					->run();
			}

			$this->symbols = !empty($symbols) ? array_column($symbols, 'symbol') : [];
		}


		return $this->symbols;
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
		if ($this->isOperatorAdmin()) {
			$properties (new Select())
				->select('string_agg(hydro_observed_property.id::text, \',\') as properties')
				->from('hydro_observed_property')
				->run(Query::FETCH_FIRST);
		} else {
			$properties = (new Select())
				->select('string_agg(hydro_observed_property.id::text, \',\') as properties')
				->from('hydro_observed_property')
				->join('hydropoint_observed_property', 'hydropoint_observed_property.observed_propertyid = hydro_observed_property.id')
				->join('hydropoint', 'hydropoint.id = hydropoint_observed_property.mpointid')
				->where("hydropoint.operatorid = {$this->operatorId}")
				->run(Query::FETCH_FIRST);
		}
		return $properties ? $properties['properties'] : '';
	}


	/**
	 * @return string
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Select
	 */
	protected function getMeteoPropertiesByOperator(): string {
		if ($this->isOperatorAdmin()) {
			$properties = (new Select())
				->select('string_agg(meteo_observed_property.id::text, \',\') as properties')
				->from('meteo_observed_property')
				->run(Query::FETCH_FIRST);
		} else {
			$properties = (new Select())
				->select('string_agg(meteo_observed_property.id::text, \',\') as properties')
				->from('meteo_observed_property')
				->join('meteopoint_observed_property', 'meteopoint_observed_property.observed_propertyid = meteo_observed_property.id')
				->join('meteopoint', 'meteopoint.id = meteopoint_observed_property.mpointid')
				->where("meteopoint.operatorid = {$this->operatorId}")
				->run(Query::FETCH_FIRST);
		}

		return $properties ? $properties['properties'] : '';
	}


	/**
	 * Checks if the provided values are permitted
	 *
	 * @param array $values
	 *
	 * @return array
	 * @throws QueryException
	 */
	public static function checkAgainstSymbols($type, array $symbols, array $availableValues): array {
		if ($type === MPOINT_TYPE_HYDRO) {
			$requestedProps = (new Select())
				->select('hydro_observed_property.id, hydro_observed_property.symbol')
				->from('hydro_observed_property')
				->whereIn('symbol', $symbols, 'symbolParam')
				->run();
		} else {
			$requestedProps = (new Select())
				->select('meteo_observed_property.id, meteo_observed_property.symbol')
				->from('meteo_observed_property')
				->whereIn('symbol', $symbols, 'symbolParam')
				->run();
		}

		$result = [];
		$unauthorized = array_diff(array_column($requestedProps, 'id'), $availableValues);
		if (!empty($unauthorized)) {
			foreach ($requestedProps as $prop) {
				if (in_array($prop['id'], $unauthorized)) {
					$result[] = $prop['symbol'];
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
			if (!$this->operatorIdentity) {
				throw new Exception('Missing operator!');
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