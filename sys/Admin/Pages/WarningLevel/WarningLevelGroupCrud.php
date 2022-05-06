<?php

namespace Environet\Sys\Admin\Pages\WarningLevel;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\WarningLevelGroupQueries;
use Environet\Sys\General\Db\Query\Delete;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\EventLogger;
use Environet\Sys\General\Exceptions\HttpNotFoundException;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Response;

/**
 * Class WarningLevelGroupCrud
 *
 * Handles CRUD operations for warning level groups.
 *
 * @package Environet\Sys\Admin\Pages\WarningLevelGroup
 * @author  SRG Group <dev@srg.hu>
 */
class WarningLevelGroupCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = WarningLevelGroupQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/warning-level-group/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/warning-level-group/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/warning-level-group/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/warning-level-groups';

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'Warning level group successfully added';

	/**
	 * @inheritdoc
	 */
	protected $successEditMessage = 'Warning level group successfully saved';


	/**
	 * @param bool $plural
	 *
	 * @return string
	 */
	protected function getEntityName(bool $plural = false): string {
		return $plural ? 'warning level groups' : 'warning level group';
	}


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data, ?array $editedRecord = null): bool {
		$valid = true;
		if (!validate($data, 'name', REGEX_ALPHANUMERIC, true)) {
			$this->addFieldMessage('name', 'The group\'s name is empty or its format is not valid', self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!WarningLevelGroupQueries::checkUnique(['name' => $data['name']], $editedRecord ? $editedRecord['id'] : null)) {
			$this->addFieldMessage('name', 'Name must be unique', self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


	/**
	 * Deletes one group (by id). Before removing the group, it's upper level usage has to be checked.
	 * If the requested group is used by any other relation, an error is thrown, otherwise the group is deleted.
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
		$group = WarningLevelGroupQueries::getById($groupId);
		if (is_null($group)) {
			throw new HttpNotFoundException('The group doesn\'t exist');
		}

		//we have to check the requested group's relations
		$warningLevelCount = (new Select())
			->select('COUNT(*)')
			->from('warning_levels')
			->where('warning_level_groupid = :groupId')
			->addParameter(':groupId', $groupId)
			->run(Query::FETCH_COUNT);

		// if it has active "upper" relation, we don't delete it
		if ($warningLevelCount > 0) {
			$this->addMessage('The requested group isn\'t deletable because it has active relation with warning levels!');
		} else {
			(new Delete())->table('warning_level_groups')->where('id = :groupId')->addParameter(':groupId', $groupId)->run();

			$this->addMessage('The requested group has been deleted!');
			// log group deleting event
			EventLogger::log(EventLogger::EVENT_TYPE_GROUP_DELETE, ['id' => $groupId]);
		}

		return $this->redirectBack('/admin/warning-level-groups');
	}


}
