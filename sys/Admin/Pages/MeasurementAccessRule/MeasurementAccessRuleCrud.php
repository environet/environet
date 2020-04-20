<?php

namespace Environet\Sys\Admin\Pages\MeasurementAccessRule;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\GroupQueries;
use Environet\Sys\General\Db\MeasurementAccessRuleQueries;
use Environet\Sys\General\Db\OperatorQueries;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\UserQueries;
use Environet\Sys\General\Exceptions\QueryException;

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
	 */
	protected function formContext(): array {

		$options = [
			'operators' => OperatorQueries::getOptionList(),
			'groups' => GroupQueries::getOptionList(),
		];

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


}
