<?php

namespace Environet\Sys\Admin\Pages\Operator;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\GroupQueries;
use Environet\Sys\General\Db\OperatorQueries;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\UserQueries;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class OperatorCrud
 *
 * Handles CRUD operations for operators.
 *
 * @package Environet\Sys\Admin\Pages\Operator
 * @author  SRG Group <dev@srg.hu>
 */
class OperatorCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = OperatorQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/operator/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/operator/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/operator/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/operators';

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'Operator successfully added';


	/**
	 * @param bool $plural
	 *
	 * @return string
	 */
	protected function getEntityName(bool $plural = false): string {
		return $plural ? 'operators' : 'operator';
	}


	/**
	 * List page action for operators.
	 *
	 * @return Response
	 * @throws RenderException
	 */
	public function list(): Response {
		try {
			$searchString = $this->request->getQueryParam('search');


			$directUserCountQuery = (new Select())->select('COUNT(*)')->from('operator_users')
												  ->where('operator_users.operatorid = operator.id')->buildQuery();
			$groupUserCountQuery = (new Select())->select('COUNT(*)')->from('operator_groups')
												 ->join('users_groups', 'users_groups.groupsid = operator_groups.groupsid')
												 ->where('operator_groups.operatorid = operator.id')->buildQuery();
			$groupCountQuery = (new Select())->select('COUNT(*)')->from('operator_groups')
											 ->where('operator_groups.operatorid = operator.id')->buildQuery();



			//Base query with joins and conditions
			$query = (new Select())
				->select('operator.*')
				->select('(' . $directUserCountQuery . ') + (' . $groupUserCountQuery . ') as user_count')
				->select('(' . $groupCountQuery . ') as group_count')
				->from('operator');

			$this->modifyListQuery($query);

			if (!is_null($searchString)) {
				$query->search(
					explode(' ', urldecode($searchString)),
					OperatorQueries::$searchableFields
				);
			}

			//Add pagination options to query, and get the page info (count, pages)
			$currentPage = $this->request->getQueryParam('page', 1);
			$query->paginate(
				self::PAGE_SIZE,
				$currentPage,
				$totalCount,
				$maxPage
			);

			//Add order by query condition
			$query->sort(
				$this->request->getQueryParam('order_by'),
				$this->request->getQueryParam('order_dir', 'ASC')
			);

			//Run query
			$operators = $query->run();
		} catch (QueryException $exception) {
			$operators = [];
		}

		$this->updateListPageState();
		$pageTitle = $this->getTitle(self::PAGE_LIST);

		return $this->render($this->indexTemplate, compact('operators', 'totalCount', 'currentPage', 'maxPage', 'searchString', 'pageTitle'));
	}


	/**
	 * @inheritDoc
	 * @throws QueryException
	 */
	protected function modifyListQuery(Select $query) {
		if (in_array('admin.operators.readown', $this->request->getIdentity()->getAuthorizedPermissions())) {
			// Get the ids of operators the user is part of

			$operators = UserQueries::getOperatorsOfUser($this->request->getIdentity()->getId());
			$query->whereIn('id', array_column($operators, 'id'), 'operatorId');
		}
	}


	/**
	 * Check if the currenty authenticated user belongs to an operator
	 *
	 * @param int $id Operator point id
	 * @return bool
	 * @throws QueryException
	 */
	private function userIsOperator(int $id): bool {
		$operatorIds = array_column(UserQueries::getOperatorsOfUser($this->request->getIdentity()->getId()), 'id');

		return in_array($id, $operatorIds);
	}


	/**
	 * @inheritDoc
	 */
	protected function formContext(): array {
		return [
			'users'  => UserQueries::getOptionList(),
			'groups' => GroupQueries::getOptionList(),
		];
	}


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data): bool {
		$valid = true;

		//Validate operator name - required, and pattern
		if (!validate($data, 'name', REGEX_ALPHANUMERIC, true)) {
			$this->addMessage(__('Operator name is empty, or format is invalid'), self::MESSAGE_ERROR);
			$valid = false;
		}
		//Validate operator email - required and pattern
		if (!validate($data, 'email', REGEX_EMAIL, true)) {
			$this->addMessage(__('Operator e-mail address is empty, or format is invalid'), self::MESSAGE_ERROR);
			$valid = false;
		}
		//Validate phone - not required and pattern
		if (!validate($data, 'phone', REGEX_PHONE)) {
			$this->addMessage(__('Phone format is invalid'), self::MESSAGE_ERROR);
			$valid = false;
		}
		//Validate url - not required and pattern
		if (!validate($data, 'url', REGEX_URL)) {
			$this->addMessage(__('URL format is invalid'), self::MESSAGE_ERROR);
			$valid = false;
		}

		$id = $this->request->getQueryParam('id');
		if (is_null($id)) {
			// insert validation

			//Validate user name - required, and pattern
			if (!validate($data, 'user_name', REGEX_NAME, true)) {
				$this->addMessage(__('User name is empty, or format is invalid'), self::MESSAGE_ERROR);
				$valid = false;
			}

			//Validate user email - required and pattern
			if (!validate($data, 'user_email', REGEX_EMAIL, true)) {
				$this->addMessage(__('User e-mail address is empty, or format is invalid'), self::MESSAGE_ERROR);
				$valid = false;
			} else {
				$userWithEmail = (new Select())->select('COUNT(*)')->from('users')
											   ->where('email = :email')
											   ->addParameter(':email', $data['user_email'])
											   ->run(Query::FETCH_COUNT);
				if ($userWithEmail > 0) {
					$this->addMessage(__('User with this e-mail already exists'), self::MESSAGE_ERROR);
					$valid = false;
				}
			}

			//Validate username - required and pattern, and unique
			if (!validate($data, 'user_username', REGEX_USERNAME, true)) {
				$this->addMessage(__('Username is empty, or format is invalid'), self::MESSAGE_ERROR);
				$valid = false;
			} else {
				$userWithUsername = (new Select())->select('COUNT(*)')->from('users')
												  ->where('username = :username')
												  ->addParameter(':username', $data['user_username'])
												  ->run(Query::FETCH_COUNT);
				if ($userWithUsername > 0) {
					$this->addMessage(__('User with this username already exists'), self::MESSAGE_ERROR);
					$valid = false;
				}
			}
		}

		return $valid;
	}


	/**
	 * @inheritDoc
	 * @throws QueryException
	 */
	protected function userCanView($id) {
		if (in_array('admin.operators.readown', $this->request->getIdentity()->getAuthorizedPermissions())) {
			return $this->userIsOperator($id);
		}
		return true;
	}


	/**
	 * @inheritDoc
	 * @throws QueryException
	 */
	protected function userCanEdit($id) {
		if (in_array('admin.operators.updateown', $this->request->getIdentity()->getAuthorizedPermissions())) {
			return $this->userIsOperator($id);
		}
		return true;
	}


}
