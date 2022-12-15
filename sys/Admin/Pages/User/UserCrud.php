<?php

namespace Environet\Sys\Admin\Pages\User;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\GroupQueries;
use Environet\Sys\General\Db\OperatorQueries;
use Environet\Sys\General\Db\PermissionQueries;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\UserQueries;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class UserCrud
 *
 * Handles CRUD operations for user management.
 *
 * @package   Environet\Sys\Admin\Pages\User
 * @author    SRG Group <dev@srg.hu>
 */
class UserCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = UserQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/user/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/user/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/user/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/users';

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'User successfully added';

	/**
	 * @inheritdoc
	 */
	protected $successEditMessage = 'User successfully saved';


	/**
	 * @param bool $plural
	 *
	 * @return string
	 */
	protected function getEntityName(bool $plural = false): string {
		return $plural ? 'users' : 'user';
	}


	/**
	 * List page action for users.
	 *
	 * @return Response
	 * @throws RenderException
	 */
	public function list(): Response {

		$searchString = $this->request->getQueryParam('search');

		try {
			//Base query with joins and conditions
			$query = (new Select())
				->select(['users.*'])
				->select('STRING_AGG(DISTINCT groups.name, \', \') as group_names')
				->from('users')
				->join('users_groups', 'users_groups.usersid = users.id', Query::JOIN_LEFT)
				->join('groups', 'users_groups.groupsid = groups.id', Query::JOIN_LEFT)
				->where('users.deleted_at IS NULL')
				->groupBy('users.id');

			if (!is_null($searchString)) {
				$query->search(
					explode(' ', urldecode($searchString)),
					UserQueries::$searchableFields
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
			$records = $query->run();
		} catch (QueryException $exception) {
			$records = [];
		}

		$this->updateListPageState();
		$pageTitle = $this->getTitle(self::PAGE_LIST);

		return $this->render('/user/index.phtml', compact('records', 'totalCount', 'currentPage', 'maxPage', 'searchString', 'pageTitle'));
	}


	/**
	 * @inheritDoc
	 */
	protected function formContext(): array {
		return [
			'permissions' => PermissionQueries::getOptionList(),
			'groups'      => GroupQueries::getOptionList(),
			'operators'   => OperatorQueries::getOptionList(),
		];
	}


	/**
	 * @inheritDoc
	 * @throws QueryException
	 */
	protected function validateData(array $data, ?array $editedRecord = null): bool {
		$userId = $this->request->getQueryParam('id');
		$valid = true;

		if (!validate($data, 'name', null, true)) {
			$this->addFieldMessage('name', 'The user\'s name is required', self::MESSAGE_ERROR);
			$valid = false;
		}

		if ($userId) {
			// update validation
			if ($data['password'] !== "") {
				// if user want to change his pw
				if ($data['password_confirm'] === "") {
					// but they left the confirm field empty
					$this->addFieldMessage('password', 'If you want to change your password, you have to set the password confirmation also.', self::MESSAGE_ERROR);
					$valid = false;
				}
				if ($data['password'] != $data['password_confirm']) {
					// if the password confirmation failed
					$this->addFieldMessage('password', 'Password confirmation is invalid', self::MESSAGE_ERROR);
					$valid = false;
				}
			}


			if (!validate($data, 'email', REGEX_EMAIL, true)) {
				$this->addFieldMessage('email', 'The user\'s e-mail address is required and should be valid e-mail address', self::MESSAGE_ERROR);
				$valid = false;
			} else {
				$userEmailInDb = (new Select())
					->select('COUNT(*)')
					->from('users')
					->where('email = :email')
					->addParameter(':email', $data['email'])
					->run(Query::FETCH_COUNT);

				$user = (new Select())
					->select(['id', 'email'])
					->from('users')
					->where('id = :id')
					->addParameter(':id', $userId)
					->run(Query::FETCH_FIRST);

				if ($userEmailInDb > 0 && $user['email'] != $data['email']) {
					$this->addFieldMessage('email', __('This e-mail is already taken'), self::MESSAGE_ERROR);
					$valid = false;
				}
			}
		} else {
			if (!validate($data, 'email', REGEX_EMAIL, true)) {
				$this->addFieldMessage('email', 'The user\'s e-mail address is required and should be valid e-mail address', self::MESSAGE_ERROR);
				$valid = false;
			} else {
				$userWithEmail = (new Select())
					->select('COUNT(*)')
					->from('users')
					->where('email = :email')
					->addParameter(':email', $data['email'])
					->run(Query::FETCH_COUNT);
				if ($userWithEmail > 0) {
					$this->addFieldMessage('email', __('User with this e-mail already exists'), self::MESSAGE_ERROR);
					$valid = false;
				}
			}

			// insert validation
			if (!validate($data, 'username', REGEX_USERNAME, true)) {
				$this->addFieldMessage('username', 'The user\'s username is required', self::MESSAGE_ERROR);
				$valid = false;
			} else {
				$userWithUsername = (new Select())
					->select('COUNT(*)')
					->from('users')
					->where('username = :username')
					->addParameter(':username', $data['username'])
					->run(Query::FETCH_COUNT);
				if ($userWithUsername > 0) {
					$this->addFieldMessage('username', __('User with this username already exists'), self::MESSAGE_ERROR);
					$valid = false;
				}
			}
		}

		return $valid;
	}


}
