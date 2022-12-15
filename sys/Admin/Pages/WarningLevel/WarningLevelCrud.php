<?php

namespace Environet\Sys\Admin\Pages\WarningLevel;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\HydroMonitoringPointQueries;
use Environet\Sys\General\Db\OperatorQueries;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\UserQueries;
use Environet\Sys\General\Db\WarningLevelGroupQueries;
use Environet\Sys\General\Db\WarningLevelQueries;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class WarningLevelCrud
 *
 * Handles CRUD operations for warning level definitions.
 *
 * @package Environet\Sys\Admin\Pages\MeasurementAccessRule
 * @author  SRG Group <dev@srg.hu>
 */
class WarningLevelCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = WarningLevelQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/warning-level/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/warning-level/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/warning-level/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/warning-levels';

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'Warning level successfully added';

	/**
	 * @inheritdoc
	 */
	protected $successEditMessage = 'Warning level successfully saved';

	/**
	 * @var string
	 */
	protected $readOwnPermissionName = 'admin.warninglevels.readown';

	/**
	 * @var string
	 */
	protected $updateOwnPermissionName = 'admin.warninglevels.updateown';

	/**
	 * @var string
	 */
	protected $createOwnPermissionName = 'admin.warninglevels.createown';


	/**
	 * @param bool $plural
	 *
	 * @return string
	 */
	protected function getEntityName(bool $plural = false): string {
		return $plural ? 'warning levels' : 'warning level';
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
			$query->whereIn('operatorid', array_column($operators, 'id'), 'operatorId');
		}

		$query->join('operator', 'operator.id = warning_levels.operatorid', Query::JOIN_INNER);
		$query->join('warning_level_groups', 'warning_level_groups.id = warning_levels.warning_level_groupid', Query::JOIN_INNER);
		$query->select('operator.name as operator_name');
		$query->select('warning_level_groups.name as warning_level_group_name');
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
			'groups'    => WarningLevelGroupQueries::getOptionList(),
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

		if (!validate($data, 'operatorid', null, true)) {
			$this->addFieldMessage('operatorid', 'Operator is required', self::MESSAGE_ERROR);
			$valid = false;
		}
		if (!validate($data, 'short_description', null, true)) {
			$this->addFieldMessage('short_description', 'Short description is required', self::MESSAGE_ERROR);
			$valid = false;
		}
		if (!validate($data, 'warning_level_groupid', null, true)) {
			$this->addFieldMessage('warning_level_groupid', 'Group is required', self::MESSAGE_ERROR);
			$valid = false;
		}
		if (!validate($data, 'color', '/[0-9A-F]/i', false)) {
			$this->addFieldMessage('color', 'Color value must be a valid RGB color definition in hexadecimal format (e.g. 55ABF1', self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!WarningLevelQueries::checkUnique(['short_description' => $data['short_description'], 'operatorid' => $data['operatorid']], $editedRecord ? $editedRecord['id'] : null)) {
			$this->addFieldMessage('short_description', sprintf('Short description must be unique for operator #%d', $data['operatorid']), self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!WarningLevelQueries::checkUnique(['color' => $data['color'], 'operatorid' => $data['operatorid']], $editedRecord ? $editedRecord['id'] : null)) {
			$this->addFieldMessage('color', sprintf('Color description must be unique for operator #%d', $data['operatorid']), self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


}
