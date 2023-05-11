<?php

namespace Environet\Sys\Admin\Pages\MeasurementAccessRule;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\GroupQueries;
use Environet\Sys\General\Db\HydroMonitoringPointQueries;
use Environet\Sys\General\Db\HydroObservedPropertyQueries;
use Environet\Sys\General\Db\MeasurementAccessRuleQueries;
use Environet\Sys\General\Db\MeteoMonitoringPointQueries;
use Environet\Sys\General\Db\MeteoObservedPropertyQueries;
use Environet\Sys\General\Db\OperatorQueries;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\UserQueries;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class DataAccessRuleCrud
 *
 * Handles CRUD operations for data access rules.
 *
 * @package Environet\Sys\Admin\Pages\MeasurementAccessRule
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

	/**
	 * @inheritdoc
	 */
	protected $successEditMessage = 'Measurement access rule successfully saved';

	/**
	 * @var string
	 */
	protected $readOwnPermissionName = 'admin.measurementaccessrules.readown';

	/**
	 * @var string
	 */
	protected $updateOwnPermissionName = 'admin.measurementaccessrules.updateown';

	/**
	 * @var string
	 */
	protected $createOwnPermissionName = 'admin.measurementaccessrules.createown';


	/**
	 * @param bool $plural
	 *
	 * @return string
	 */
	protected function getEntityName(bool $plural = false): string {
		return $plural ? 'measurement access rules' : 'measurement access rule';
	}


	/**
	 * @param Select $query
	 *
	 * @return bool|void
	 * @throws QueryException
	 */
	protected function modifyListQuery(Select $query) {
		if (in_array($this->readOwnPermissionName, $this->request->getIdentity()->getAuthorizedPermissions())) {
			// Get the ids of operators the user is part of
			$operators = UserQueries::getOperatorsOfUser($this->request->getIdentity()->getId());
			$query->whereIn('operator_id', array_column($operators, 'id'), 'operatorId');
		}

		$query->join('operator', 'operator.id = measurement_access_rules.operator_id', Query::JOIN_INNER);
		$query->select('operator.name as operator_name');
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
	 *
	 * @param array|null $record
	 *
	 * @return Response
	 * @throws RenderException
	 */
	protected function renderForm(array $record = null): Response {
		if ($this->request->isPost()) {
			$_POST['monitoringpoint_selector'] = implode(',', $_POST['monitoringpoint_selector']);
			$_POST['observed_property_selector'] = implode(',', $_POST['observed_property_selector']);
		}

		return parent::renderForm($record);
	}


	/**
	 * @inheritDoc
	 * @throws QueryException
	 */
	protected function formContext(): array {

		$options = [
			'operators' => OperatorQueries::getOptionList(),
			'groups'    => GroupQueries::getOptionList(),
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
	 * @inheritDoc
	 */
	protected function validateData(array $data, ?array $editedRecord = null): bool {
		$valid = true;

		if (!validate($data, 'operator', null, true)) {
			$this->addFieldMessage('operator', 'Operator is required', self::MESSAGE_ERROR);
			$valid = false;
		}
		if (!(is_array($data['monitoringpoint_selector']) && !empty($data['monitoringpoint_selector']))) {
			$this->addFieldMessage('monitoringpoint_selector', 'One or more monitoring point must be selected', self::MESSAGE_ERROR);
			$valid = false;
		}
		if (!(is_array($data['observed_property_selector']) && !empty($data['observed_property_selector']))) {
			$this->addFieldMessage('observed_property_selector', 'One or more observed property must be selected', self::MESSAGE_ERROR);
			$valid = false;
		}
		if (!(is_array($data['groups']) && !empty($data['groups']))) {
			$this->addFieldMessage('groups', 'One or more group must be selected', self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!is_numeric($data['interval_years'])) {
			$this->addFieldMessage('interval_years', 'Years must be a number', self::MESSAGE_ERROR);
			$valid = false;
		}
		if (!is_numeric($data['interval_months'])) {
			$this->addFieldMessage('interval_months', 'Months must be a number', self::MESSAGE_ERROR);
			$valid = false;
		}
		if (!is_numeric($data['interval_days'])) {
			$this->addFieldMessage('interval_days', 'Days must be a number', self::MESSAGE_ERROR);
			$valid = false;
		}

		if ((isset($data['interval_days']) && isset($data['interval_months']) && isset($data['interval_years'])) &&
			($data['interval_days'] + $data['interval_months'] + $data['interval_years']) === 0) {
			$this->addFieldMessage('intervals', 'One of intervals must be greater than zero', self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
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
			$hydroQuery->where("UPPER(name) LIKE UPPER('%$search%')");
			$meteoQuery->where("UPPER(name) LIKE UPPER('%$search%')");
		}
		$meteoPoints = $meteoQuery->run();
		$hydroPoints = $hydroQuery->run();
		$results = [];
		foreach ($hydroPoints as $hydroPoint) {
			$results[] = [
				'value' => 'hydro_' . $hydroPoint['id'],
				'name'  => $hydroPoint['name'] . ' (HYDRO)'
			];
		}
		foreach ($meteoPoints as $meteoPoint) {
			$results[] = [
				'value' => 'meteo_' . $meteoPoint['id'],
				'name'  => $meteoPoint['name'] . ' (METEO)'
			];
		}

		// Sort points by name
		usort($results, function ($a, $b) {
			return strcasecmp($a['name'], $b['name']);
		});

		return new Response(json_encode($results));
	}


	/**
	 * Get properites of an operator with ajax request for select options
	 *
	 * @return Response
	 * @throws QueryException
	 */
	public function operatorProperties() {
		$type = $_GET['type'] ?: false;
		$operator = trim($this->request->getQueryParam('operator'));

		$results = [];

		if ($type === false || $type === 'hydro') {
			$hydroQuery = (new Select())->select(['DISTINCT(symbol)', 'hydro_observed_property.id'])->from('hydro_observed_property')
				->join('hydropoint_observed_property', 'hydropoint_observed_property.observed_propertyid = hydro_observed_property.id')
				->join('hydropoint', 'hydropoint.id = hydropoint_observed_property.mpointid');
			if ($operator) {
				$hydroQuery->where('hydropoint.operatorid = :operatorid')->addParameter('operatorid', $operator);
			}
			$hydroProperties = $hydroQuery->run();
			foreach ($hydroProperties as $hydroProperty) {
				$results[] = [
					'value' => 'hydro_' . $hydroProperty['id'],
					'name'  => $hydroProperty['symbol']
				];
			}
		}

		if ($type === false || $type === 'meteo') {
			$meteoQuery = (new Select())->select(['DISTINCT(symbol)', 'meteo_observed_property.id'])->from('meteo_observed_property')
				->join('meteopoint_observed_property', 'meteopoint_observed_property.observed_propertyid = meteo_observed_property.id')
				->join('meteopoint', 'meteopoint.id = meteopoint_observed_property.mpointid');
			if ($operator) {
				$meteoQuery->where('meteopoint.operatorid = :operatorid')->addParameter('operatorid', $operator);
			}
			$meteoProperties = $meteoQuery->run();
			foreach ($meteoProperties as $meteoProperty) {
				$results[] = [
					'value' => 'meteo_' . $meteoProperty['id'],
					'name'  => $meteoProperty['symbol']
				];
			}
		}

		// Sort properties by symbol
		usort($results, function ($a, $b) {
			return strcasecmp($a['name'], $b['name']);
		});

		return new Response(json_encode($results));
	}


	/**
	 * Update points and properties, show the values instead of ids
	 *
	 * @param array $records
	 */
	protected function modifyRecords(array &$records) {
		$hydroPoints = HydroMonitoringPointQueries::getOptionList();
		$meteoPoints = MeteoMonitoringPointQueries::getOptionList();
		$hydroProperties = HydroObservedPropertyQueries::getOptionList('symbol');
		$meteoProperties = MeteoObservedPropertyQueries::getOptionList('symbol');

		foreach ($records as &$record) {
			if (!empty($record['monitoringpoint_selector']) && $record['monitoringpoint_selector'] !== '*') {
				$record['monitoringpoint_selector'] = implode(', ', array_map(function ($item) use ($hydroPoints, $meteoPoints) {
					if (preg_match('/^(meteo|hydro)_(.*)$/', $item, $m)) {
						return ${$m[1] . 'Points'}[$m[2]] ?? $item;
					} else {
						return $hydroPoints[$item] ?? $item;
					}
				}, explode(',', $record['monitoringpoint_selector'])));
			}
			if (!empty($record['observed_property_selector']) && $record['observed_property_selector'] !== '*') {
				$record['observed_property_selector'] = implode(', ', array_map(function ($item) use ($hydroProperties, $meteoProperties) {
					if (preg_match('/^(meteo|hydro)_(.*)$/', $item, $m)) {
						return ${$m[1] . 'Properties'}[$m[2]] ?? $item;
					} else {
						return $hydroPoints[$item] ?? $item;
					}
				}, explode(',', $record['observed_property_selector'])));
			}
		}
	}


}
