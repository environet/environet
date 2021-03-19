<?php

namespace Environet\Sys\Admin\Pages;

use Environet\Sys\General\Db\BaseQueries;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\HttpBadRequestException;
use Environet\Sys\General\Exceptions\HttpNotFoundException;
use Environet\Sys\General\Exceptions\InvalidConfigurationException;
use Environet\Sys\General\Exceptions\MissingEventTypeException;
use Environet\Sys\General\Exceptions\PermissionException;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;
use Exception;

/**
 * Class CrudPage
 *
 * Base class for admin area pages handling CRUD operations
 *
 * @package Environet\Sys\Admin\Pages
 * @author  SRG Group <dev@srg.hu>
 */
abstract class CrudPage extends BasePage {

	const PAGE_LIST = 'list';
	const PAGE_ADD  = 'add';
	const PAGE_EDIT = 'edit';
	const PAGE_SHOW = 'show';

	/**
	 * Relative path to the index template file.
	 * @var string
	 */
	protected $indexTemplate;

	/**
	 * The query class of the current page.
	 * @var BaseQueries
	 */
	protected $queriesClass;

	/**
	 * Success add message.
	 * @var string
	 */
	protected $successAddMessage;

	/**
	 * Template file for edit and add operations.
	 * @var string
	 */
	protected $formTemplate;

	/**
	 * Template file for show page.
	 * @var string
	 */
	protected $showTemplate;

	/**
	 * Relative path to the list page.
	 * @var string
	 */
	protected $listPagePath;


	/**
	 * @param bool $plural
	 *
	 * @return string|null
	 */
	abstract protected function getEntityName(bool $plural = false): string;


	/**
	 * Build the title of the page
	 *
	 * @param string     $pageType
	 * @param array|null $record
	 *
	 * @return string
	 */
	protected function getTitle(string $pageType, ?array $record = null) {
		$recordTitle = [];
		if ($record) {
			if (!empty($record['id'])) {
				$recordTitle[] = '#' . $record['id'];
			}
			if (!empty($record['name'])) {
				$recordTitle[] = $record['name'];
			}
		}
		$recordTitle = implode(' - ', $recordTitle);
		switch ($pageType) {
			case self::PAGE_LIST:
				return ucfirst($this->getEntityName(true));
			case self::PAGE_ADD:
				return 'Add ' . $this->getEntityName();
			case self::PAGE_SHOW:
				$name = ucfirst($this->getEntityName());

				return "$name: $recordTitle";
			case self::PAGE_EDIT:
				return "Edit {$this->getEntityName()}: $recordTitle";
		}
	}


	/**
	 * Common function to render the list page.
	 *
	 * @return Response
	 * @throws RenderException
	 */
	protected function renderListPage(): Response {
		try {
			// get search param from query string
			$searchString = $this->request->getQueryParam('search');

			//Base query with joins and conditions
			$query = (new Select())
				->select($this->queriesClass::$tableName . '.*')
				->from($this->queriesClass::$tableName);

			$this->modifyListQuery($query);

			if (!is_null($searchString)) {
				$query->search(
					explode(' ', urldecode($searchString)),
					$this->queriesClass::$searchableFields
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

		$listFilters = $this->getListFilters();
		$pageTitle = $this->getTitle(self::PAGE_LIST);

		return $this->render($this->indexTemplate, compact('records', 'totalCount', 'currentPage', 'maxPage', 'searchString', 'listFilters', 'pageTitle'));
	}


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
	 * Common function to render the show page.
	 *
	 * @return Response
	 * @throws RenderException
	 * @throws HttpNotFoundException
	 * @throws PermissionException
	 */
	protected function renderShowPage(): Response {
		$id = $this->getIdParam();
		$record = $this->getRecordById($id);

		if (!$this->userCanView($id)) {
			throw new PermissionException("You don't have permission to view record with id: '$id'");
		}

		$listPage = $this->getListPageLinkWithState();
		$pageTitle = $this->getTitle(self::PAGE_SHOW, $record);

		return $this->render($this->showTemplate, compact('record', 'listPage', 'pageTitle'));
	}


	/**
	 * Handle post data in case of update or add method.
	 *
	 * @param null $id
	 * @param null $record
	 *
	 * @return Response
	 * @throws HttpBadRequestException
	 * @throws QueryException
	 * @throws RenderException
	 * @throws MissingEventTypeException
	 * @throws InvalidConfigurationException
	 */
	protected function handleFormPost($id = null, $record = null): Response {
		$postData = $this->request->getCleanData();

		if (!$this->checkCsrf()) {
			// if the csrf token isn't valid
			throw new HttpBadRequestException('CSRF validation failed');
		}

		if (!$this->validateData($postData, $record)) {
			// if data isn't valid, render the form again with error messages
			if (!empty($this->fieldMessages) && empty($this->messages[self::MESSAGE_ERROR])) {
				$this->addMessage('Form has some invalid data', self::MESSAGE_ERROR);
			}

			return $this->renderForm($record);
		}

		//Data is valid, save it, add success message, and redirect to index page
		$this->queriesClass::save($postData, $id);
		$this->addMessage($this->successAddMessage, self::MESSAGE_SUCCESS);

		return $this->redirect($this->getListPageLinkWithState());
	}


	/**
	 * Common function to handle add method.
	 *
	 * @return Response
	 * @throws HttpBadRequestException
	 * @throws QueryException
	 * @throws RenderException
	 * @throws MissingEventTypeException
	 * @throws InvalidConfigurationException
	 */
	public function add(): Response {
		if ($this->request->isPost()) {
			return $this->handleFormPost();
		}

		return $this->renderForm();
	}


	/**
	 * Common function to handle edit method.
	 *
	 * @return Response
	 * @throws HttpBadRequestException
	 * @throws HttpNotFoundException
	 * @throws InvalidConfigurationException
	 * @throws MissingEventTypeException
	 * @throws PermissionException
	 * @throws QueryException
	 * @throws RenderException
	 */
	public function edit(): Response {
		$id = $this->getIdParam();
		$record = $this->getRecordById($id);

		if (!$this->userCanEdit($id)) {
			throw new PermissionException("You don't have permission to edit record with id: '$id'");
		}

		if ($this->request->isPost()) {
			return $this->handleFormPost($id, $record);
		}

		return $this->renderForm($record);
	}


	/**
	 * Common function to handle show method.
	 *
	 * @return Response
	 * @throws HttpNotFoundException
	 * @throws RenderException
	 * @throws PermissionException
	 */
	public function show(): Response {
		return $this->renderShowPage();
	}


	/**
	 * @return string
	 * @throws HttpNotFoundException
	 */
	protected function getIdParam(): string {
		$id = $this->request->getQueryParam('id');
		if (is_null($id)) {
			// if id doesn't exist, return 404
			throw new HttpNotFoundException('Query parameter \'id\' is missing');
		}

		return $id;
	}


	/**
	 * @param $id
	 *
	 * @return array|null
	 * @throws HttpNotFoundException
	 */
	protected function getRecordById($id) {
		$record = $this->queriesClass::getById($id);
		if (is_null($record)) {
			throw new HttpNotFoundException('Record with id: ' . $id . ' could not be found');
		}

		return $record;
	}


	/**
	 * Common function to delete an item.
	 *
	 * @return Response
	 * @throws HttpNotFoundException
	 */
	public function delete() {
		$id = $this->getIdParam();

		try {
			$this->queriesClass::delete($id);
			$this->addMessage('The requested item has been deleted!', self::MESSAGE_SUCCESS);
		} catch (Exception $exception) {
			$this->addMessage($exception->getMessage(), self::MESSAGE_ERROR);
		}

		return $this->redirect($this->getListPageLinkWithState());
	}


	/**
	 * If we have to render a form page, we can add more variables to the template with FormContext.
	 *
	 * @param array|null $record
	 *
	 * @return Response
	 * @throws RenderException
	 */
	protected function renderForm(array $record = null): Response {
		$pageTitle = $this->getTitle($record ? self::PAGE_EDIT : self::PAGE_ADD, $record);

		$context = array_merge([
			'record'    => $record,
			'listPage'  => $this->getListPageLinkWithState(),
			'pageTitle' => $pageTitle
		], $this->formContext());

		return $this->render($this->formTemplate, $context);
	}


	/**
	 * Return key of base path of CRUD
	 */
	protected function getBasePathKey(): string {
		$pathParts = $this->request->getPathParts();
		$pathParts = array_filter($pathParts, function ($part) {
			return !in_array($part, ['show', 'edit', 'add', 'delete']);
		});

		return implode('_', $pathParts);
	}


	/**
	 * Read list page state from session
	 */
	protected function getListPageState(): ?array {
		return $_SESSION['listPageStates'][$this->getBasePathKey()] ?? null;
	}


	/**
	 * Update list page state in session
	 */
	protected function updateListPageState() {
		$params = $this->request->getQueryParams();
		$stateFields = ['page', 'order_by', 'order_dir', 'search'];
		if (($filterFields = $this->getListFilters())) {
			$stateFields = array_merge($stateFields, array_keys($filterFields));
		}
		$params = array_filter($params, function ($param) use ($stateFields) {
			return !in_array($param, $stateFields);
		});
		$_SESSION['listPageStates'][$this->getBasePathKey()] = $params;
	}


	/**
	 * Get url of list page with state
	 */
	protected function getListPageLinkWithState(): string {
		$path = $this->listPagePath;
		if (($listPageState = $this->getListPageState())) {
			$separator = strpos($path, '?') !== false ? '&' : '?';
			$listPageState = array_filter(array_map(function ($item) {
				return urlencode($item);
			}, $listPageState));
			$path .= $listPageState ? $separator . http_build_query($listPageState) : '';
		}

		return $path;
	}


	/**
	 * Override to add context variables to be used in the form template
	 *
	 * @return array
	 */
	protected function formContext(): array {
		return [];
	}


	/**
	 * Validate the form's data, return a boolean response
	 *
	 * @param array      $data Form's data
	 * @param array|null $editedRecord
	 *
	 * @return bool Valid state
	 */
	protected function validateData(array $data, ?array $editedRecord = null): bool {
		return true;
	}


	/**
	 * Check if the currently authenticated user has permission to view a record with a given id
	 *
	 * @param mixed $id
	 *
	 * @return bool Has permission
	 */
	protected function userCanView($id) {
		return true;
	}


	/**
	 * Check if user has permission to edit a record with a given id
	 *
	 * @param mixed $id
	 *
	 * @return bool Has permission
	 */
	protected function userCanEdit($id) {
		return true;
	}


	protected function modifyListQuery(Select $query) {
		return true;
	}


	/**
	 * Get array of filter configirations for list page
	 * @return array|null
	 */
	protected function getListFilters(): ?array {
		return null;
	}


}
