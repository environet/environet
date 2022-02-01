<?php


namespace Environet\Sys\General\Db\Selectors;


use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Identity;

/**
 * Class BaseAccessSelector
 *
 * Base selector for MonitoringPointSelector and ObservedPropertySelector
 *
 * @package Environet\Sys\General\Db\Selectors
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
abstract class BaseAccessSelector extends Selector {

	/**
	 * @var int
	 */
	protected $type;

	/**
	 * @var Identity|null
	 */
	protected $operatorId;

	/**
	 * @var Identity|null
	 */
	protected $operatorIdentity;


	/**
	 * BaseAccessSelector constructor.
	 *
	 * @param string $values
	 * @param        $type
	 * @param int    $operatorId
	 *
	 * @throws QueryException
	 */
	public function __construct(string $values, $type, int $operatorId) {
		$this->type = $type;
		$this->operatorIdentity = $operatorId ? $this->getOperatorIdentity($operatorId) : null;
		$this->operatorId = $operatorId;

		parent::__construct($values, self::SELECTOR_TYPE_INT);
	}


	/**
	 * Determine if the currently stored identity is an admin.
	 *
	 * @return bool
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Identity::hasPermissions()
	 */
	protected function isOperatorAdmin(): bool {
		return $this->operatorIdentity->hasPermissions([Identity::ADMIN_PERMISSION]);
	}


	/**
	 * @param $serialized
	 */
	public function unserialize($serialized) {
		if (is_string($serialized)) {
			$strType = $this->type === MPOINT_TYPE_HYDRO ? 'hydro' : 'meteo';
			$this->values = array_filter(array_map(function ($value) use ($strType) {
				return intval(preg_replace('/^' . $strType . '_(\d+)$/', '$1', $value)) ?: null;
			}, explode(',', $serialized)));
		}
	}


}