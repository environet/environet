<?php

namespace Environet\Sys\Admin\Pages\MeasurementAccessRule;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\GroupQueries;
use Environet\Sys\General\Db\HydroMonitoringPointQueries;
use Environet\Sys\General\Db\MeasurementAccessRuleQueries;
use Environet\Sys\General\Db\MeteoMonitoringPointQueries;
use Environet\Sys\General\Db\OperatorQueries;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\UserQueries;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Response;

/**
 * Class DataAccessRuleCrud
 *
 * Handles CRUD operations for data access rules.
 *
 * @package Environet\Sys\Admin\Pages\DataProvider
 * @author  SRG Group <dev@srg.hu>
 */
class MeasurementAccessRuleCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = MeasurementAccessRuleQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/measurement-access-rule/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/measurement-access-rule/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/measurement-access-rule/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/measurement-access-rules';

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'Measurement access rule successfully added';

	protected $readOwnPermissionName = 'admin.measurementaccessrules.readown';

	protected $updateOwnPermissionName = 'admin.measurementaccessrules.updateown';

	protected $createOwnPermissionName = 'admin.measurementaccessrules.createown';


	/**
	 * @param Select $query
	 * @return bool|void
	 * @throws QueryException
	 */
	protected function modifyListQuery(Select $query) {
		if (in_array($this->readOwnPermissionName, $this->request->getIdentity()->getAuthorizedPermissions())) {
			// Get the ids of operators the user is part of
			$operators = UserQueries::getOperatorsOfUser($this->request->getIdentity()->getId());
			$query->whereIn('operator_id', array_column($operators, 'id'), 'operatorId');
		}
	}


	/**
	 * @inheritDoc
	 * @throws QueryException
	 */
	protected function userCanView($id) {
		if (in_array($this->readOwnPermissionName, $this->request->getIdentity()->getAuthorizedPermissions())) {
			$operatorIds = UserQueries::getOperatorsOfUser($this->request->getIdentity()->getId());
			return in_array($id, $operatorIds);
		}
		return true;
	}


	/**
	 * @inheritDoc
	 * @throws QueryException
	 */
	protected function formContext(): array {

		$options = [
			'operators' => OperatorQueries::getOptionList(),
			'groups' => GroupQueries::getOptionList(),
		];

		// If the form is loaded for a user with limited permissions, the selectable options for the "operator" must be limited to the ones they have access to
		if (in_array($this->createOwnPermissionName, $this->request->getIdentity()->getAuthorizedPermissions())
			|| in_array($this->updateOwnPermissionName, $this->request->getIdentity()->getAuthorizedPermissions())) {
			$operatorIds = UserQueries::getOperatorsOfUser($this->request->getIdentity()->getId());

			$records = (new Select())
				->from('operator')
				->select(['operator.id', 'operator.name'])
				->whereIn('id', array_column($operatorIds, 'id'), 'operatorId')
				->run();
			$records = array_combine(array_column($records, 'id'), array_column($records, 'name'));

			$options['operators'] = $records ?: [];
		}

		return $options;
	}


	/**
	 * Get points of an operator with ajax request for select options
	 *
	 * @return Response
	 * @throws QueryException
	 */
	public function operatorPoints() {
		$search = trim($this->request->getQueryParam('search'));
		$operator = trim($this->request->getQueryParam('operator'));
		$hydroQuery = (new Select())->select(['name', 'id'])->from(HydroMonitoringPointQueries::$tableName);
		$meteoQuery = (new Select())->select(['name', 'id'])->from(MeteoMonitoringPointQueries::$tableName);
		if ($operator) {
			$hydroQuery->where('operatorid = :operatorid')->addParameter('operatorid', $operator);
			$meteoQuery->where('operatorid = :operatorid')->addParameter('operatorid', $operator);
		}
		if ($search) {
			$hydroQuery->where('name LIKE %'.$search.'%');
			$meteoQuery->where('name LIKE %'.$search.'%');
		}
		$meteoPoints = $meteoQuery->run();
		$hydroPoints = $hydroQuery->run();
		$results = [];
		foreach ($hydroPoints as $hydroPoint) {
			$results[] = [
				'value' => $hydroPoint['id'],
				'name' => $hydroPoint['name']
			];
		}
		foreach ($meteoPoints as $meteoPoint) {
			$results[] = [
				'value' => $meteoPoint['id'],
				'name' => $meteoPoint['name']
			];
		}
		return new Response(json_encode($results));
	}


	/**
	 * Get properites of an operator with ajax request for select options
	 *
	 * @return Response
	 * @throws QueryException
	 */
	public function operatorProperties() {
		$operator = trim($this->request->getQueryParam('operator'));
		$hydroQuery = (new Select())->select(['DISTINCT(symbol)', 'hydro_observed_property.id'])->from('hydro_observed_property')
			->join('hydropoint_observed_property', 'hydropoint_observed_property.observed_propertyid = hydro_observed_property.id')
			->join('hydropoint', 'hydropoint.id = hydropoint_observed_property.mpointid');
		$meteoQuery = (new Select())->select(['DISTINCT(symbol)', 'meteo_observed_property.id'])->from('meteo_observed_property')
			->join('meteopoint_observed_property', 'meteopoint_observed_property.meteo_observed_propertyid = meteo_observed_property.id')
			->join('meteopoint', 'meteopoint.id = meteopoint_observed_property.meteopointid');
		if ($operator) {
			$hydroQuery->where('hydropoint.operatorid = :operatorid')->addParameter('operatorid', $operator);
			$meteoQuery->where('meteopoint.operatorid = :operatorid')->addParameter('operatorid', $operator);
		}
		$meteoProperties = $meteoQuery->run();
		$hydroProperties = $hydroQuery->run();
		$results = [];
		foreach ($hydroProperties as $hydroProperty) {
			$results[] = [
				'value' => $hydroProperty['id'],
				'name' => $hydroProperty['symbol']
			];
		}
		foreach ($meteoProperties as $meteoProperty) {
			$results[] = [
				'value' => $meteoProperty['id'],
				'name' => $meteoProperty['symbol']
			];
		}

		return new Response(json_encode($results));
	}


}
