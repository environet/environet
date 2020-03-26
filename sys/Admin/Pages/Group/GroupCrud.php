<?php

namespace Environet\Sys\Admin\Pages\Group;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\GroupQueries;
use Environet\Sys\General\Db\PermissionQueries;
use Environet\Sys\General\Db\Query\Delete;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\EventLogger;
use Environet\Sys\General\Exceptions\HttpNotFoundException;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class GroupCrud
 *
 * @package Environet\Sys\Admin\Pages\Group
 * @author  Mate Kovacs <mate.kovacs@srg.hu>
 */
class GroupCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = GroupQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/group/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/group/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/groups';

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'Group successfully saved';


	/**
	 * List page action.
	 *
	 * @return Response
	 * @throws RenderException
	 */
	public function list(): Response {
		return $this->renderListPage();
	}


	/**
	 * @inheritDoc
	 */
	protected function formContext(): array {
		return [
			'permissions' => PermissionQueries::getOptionList(),
		];
	}


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data): bool {
		$valid = true;
		if (!validate($data, 'name', REGEX_NAME, true)) {
			$this->addMessage('The group\'s name is empty or its format is not valid', self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


	/**
	 * Group deleting. Before we remove the group, we have to check its usage in upper level.
	 * If the requested group is used by any other relation, we throw error, otherwise delete the group.
	 *
	 * @return Response|void
	 * @throws QueryException
	 * @throws HttpNotFoundException
	 */
	public function delete() {
		$groupId = $this->request->getQueryParam('id');
		if (is_null($groupId)) {
			// if the groupId doesn't exist
			throw new HttpNotFoundException('Query parameter \'id\' is missing');
		}
		$group = GroupQueries::getById($groupId);
		if (is_null($group)) {
			throw new HttpNotFoundException('The group doesn\'t exist');
		}

		// we have to check the requested group's relations
		$userGroupCount = (new Select())
			->select('COUNT(*)')
			->from('users_groups')
			->where('groupsid = :groupId')
			->addParameter(':groupId', $groupId)
			->run(Query::FETCH_COUNT);

		$operatorGroupCount = (new Select())
			->select('COUNT(*)')
			->from('operator_groups')
			->where('groupsid = :groupId')
			->addParameter(':groupId', $groupId)
			->run(Query::FETCH_COUNT);

		// if it has active "upper" relation, we don't delete it
		if ($userGroupCount > 0 || $operatorGroupCount > 0) {
			$this->addMessage('The requested group isn\'t deletable because it has active relation with operators or/and users!');
		} else {
			// otherwise delete the groups and the relations under the group
			(new Delete())->table('group_permissions')->where('groupsid = :groupId')->addParameter(':groupId', $groupId)->run();
			(new Delete())->table('groups')->where('id = :groupId')->addParameter(':groupId', $groupId)->run();

			$this->addMessage('The requested group has been deleted!');
			// log group deleting event
			EventLogger::log(EventLogger::EVENT_TYPE_GROUP_DELETE, ['id' => $groupId]);
		}

		return $this->redirectBack('/admin/groups');
	}


}
