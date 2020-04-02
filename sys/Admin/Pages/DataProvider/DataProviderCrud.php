<?php

namespace Environet\Sys\Admin\Pages\DataProvider;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\GroupQueries;
use Environet\Sys\General\Db\OperatorQueries;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\UserQueries;
use Environet\Sys\General\Exceptions\HttpNotFoundException;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class DataProviderCrud
 *
 * Handles CRUD operations for data providers.
 *
 * @package Environet\Sys\Admin\Pages\DataProvider
 * @author  SRG Group <dev@srg.hu>
 */
class DataProviderCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = OperatorQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/dataprovider/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/dataprovider/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/dataprovider/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/data-providers';

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'Data provider successfully added';


	/**
	 * List page action for data providers.
	 *
	 * @return Response
	 * @throws RenderException
	 */
	public function list(): Response {
		try {
			$searchString = $this->request->getQueryParam('search');

			//Base query with joins and conditions
			$directUserCountQuery = (new Select())->select('COUNT(*)')->from('operator_users')
			                                      ->where('operator_users.operatorid = operator.id')->buildQuery();
			$groupUserCountQuery = (new Select())->select('COUNT(*)')->from('operator_groups')
			                                     ->join('users_groups', 'users_groups.groupsid = operator_groups.groupsid')
			                                     ->where('operator_groups.operatorid = operator.id')->buildQuery();
			$groupCountQuery = (new Select())->select('COUNT(*)')->from('operator_groups')
			                                 ->where('operator_groups.operatorid = operator.id')->buildQuery();
			$query = (new Select())
				->select('operator.*')
				->select('(' . $directUserCountQuery . ') + (' . $groupUserCountQuery . ') as user_count')
				->select('(' . $groupCountQuery . ') as group_count')
				->from('operator');


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

		return $this->render($this->indexTemplate, compact('operators', 'totalCount', 'currentPage', 'maxPage', 'searchString'));
	}


	/**
	 * Show page action for data providers.
	 *
	 * @return Response
	 * @throws RenderException
	 * @throws HttpNotFoundException
	 */
	public function show(): Response {
		return $this->renderShowPage();
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
		if (!validate($data, 'name', REGEX_NAME, true)) {
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


}
