<?php

namespace Environet\Sys\Admin\Pages;

use Environet\Sys\General\Db\BaseQueries;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\HttpBadRequestException;
use Environet\Sys\General\Exceptions\HttpNotFoundException;
use Environet\Sys\General\Exceptions\MissingEventTypeException;
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

		return $this->render($this->indexTemplate, compact('records', 'totalCount', 'currentPage', 'maxPage', 'searchString'));
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
	 * Common function to render the list page.
	 *
	 * @return Response
	 * @throws RenderException
	 * @throws HttpNotFoundException
	 */
	protected function renderShowPage(): Response {
		$id = $this->getIdParam();
		$record = $this->getRecordById($id);

		return $this->render($this->showTemplate, compact('record'));
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
	 */
	protected function handleFormPost($id = null, $record = null): Response {
		$postData = $this->request->getCleanData();

		if (!$this->checkCsrf()) {
			// if the csrf token isn't valid
			throw new HttpBadRequestException('CSRF validation failed');
		}

		if (!$this->validateData($postData)) {
			// if data isn't valid, render the form again with error messages
			return $this->renderForm($record);
		}

		//Data is valid, save it, add success message, and redirect to index page
		$this->queriesClass::save($postData, $id);
		$this->addMessage($this->successAddMessage, self::MESSAGE_SUCCESS);

		return $this->redirect($this->listPagePath);
	}


	/**
	 * Common function to handle add method.
	 *
	 * @return Response
	 * @throws HttpBadRequestException
	 * @throws QueryException
	 * @throws RenderException
	 * @throws MissingEventTypeException
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
	 * @throws QueryException
	 * @throws RenderException
	 * @throws MissingEventTypeException
	 */
	public function edit(): Response {
		$id = $this->getIdParam();
		$record = $this->getRecordById($id);

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
	 */
	public function show(): Response {
		return $this->renderShowPage();
	}


	/**
	 * @return mixed|null
	 * @throws HttpNotFoundException
	 */
	private function getIdParam() {
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
	private function getRecordById($id) {
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

		return $this->redirect($this->listPagePath);
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
		$context = array_merge(['record' => $record], $this->formContext());

		return $this->render($this->formTemplate, $context);
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
	 * @param array $data Form's data
	 *
	 * @return bool Valid state
	 */
	protected function validateData(array $data): bool {
		return true;
	}


}
