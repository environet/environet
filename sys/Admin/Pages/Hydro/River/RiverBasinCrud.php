<?php

namespace Environet\Sys\Admin\Pages\Hydro\River;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\RiverBasinQueries;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class RiverBasinCrud
 *
 * Handles CRUD operations for hydropoint river basins.
 *
 * @package Environet\Sys\Admin\Pages\Hydro\River
 * @author  SRG Group <dev@srg.hu>
 */
class RiverBasinCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = RiverBasinQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/hydro/river-basin/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/hydro/river-basin/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/hydro/river-basin/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/hydro/river-basins';

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'River basin successfully added';

	/**
	 * @inheritdoc
	 */
	protected $successEditMessage = 'River basin successfully saved';


	/**
	 * @param bool $plural
	 *
	 * @return string
	 */
	protected function getEntityName(bool $plural = false): string {
		return $plural ? 'river basins' : 'river basin';
	}


	/**
	 * Show page action.
	 *
	 * @return Response
	 * @throws RenderException
	 * @uses \httpErrorPage()
	 */
	public function show(): Response {
		// Get id from url and get record by id
		$id = $this->request->getQueryParam('id');
		if (is_null($id)) {
			// if id doesn't exist, return 404
			return httpErrorPage(404);
		}

		$record = $this->queriesClass::getById($id, 'id');
		if (is_null($record)) {
			// if the requested record doesn't exist, return 404
			return httpErrorPage(404);
		}

		$listPage = $this->getListPageLinkWithState();
		$pageTitle = $this->getTitle(self::PAGE_SHOW, $record);

		return $this->render($this->showTemplate, compact('record', 'listPage', 'pageTitle'));
	}


	/**
	 * Common function to handle edit method.
	 *
	 * @return Response
	 * @throws RenderException
	 * @uses \httpErrorPage()
	 */
	public function edit(): Response {
		$id = $this->request->getQueryParam('id');
		if (is_null($id)) {
			// if id doesn't exist, return 404
			return httpErrorPage(404);
		}
		$record = $this->queriesClass::getById($id, 'id');
		if (is_null($record)) {
			// if record doesn't exist, return 404
			return httpErrorPage(404);
		}

		if ($this->request->isPost()) {
			return self::handleFormPost($id, $record);
		}

		return $this->renderForm($record);
	}


	/**
	 * Handle post data in case of update or add method.
	 *
	 * @param null $id
	 * @param null $record
	 *
	 * @return Response
	 * @throws RenderException
	 * @uses \httpErrorPage()
	 */
	protected function handleFormPost($id = null, $record = null): Response {
		$postData = $this->request->getCleanData();

		if (!$this->validateData($postData, $record)) {
			// if data isn't valid, render the form again with error messages
			return $this->renderForm($record);
		}

		if (!$this->checkCsrf()) {
			// if the csrf token isn't valid
			return httpErrorPage(400);
		}

		if (is_null($id)) {
			$postData = [
				'name' => $postData['name'] ?? null,
				'id'   => $postData['id'] ?? null,
			];
		} else {
			$postData = [
				'name' => $postData['name'] ?? null
			];
		}

		//Data is valid, save it, add success message, and redirect to index page
		try {
			$this->queriesClass::save($postData, $id, 'id');
			$this->addMessage(is_null($id) ? $this->successAddMessage : $this->successEditMessage, self::MESSAGE_SUCCESS);

			return $this->redirect($this->listPagePath);
		} catch (QueryException $e) {
			$this->addMessage('Error while saving data: ' . $e->getMessage(), self::MESSAGE_ERROR);

			return $this->renderForm();
		}
	}


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data, ?array $editedRecord = null): bool {
		$id = $this->request->getQueryParam('id');
		$valid = true;

		if (is_null($id)) {
			if (!validate($data, 'id', REGEX_RIVERBASINCODE, true)) {
				$this->addFieldMessage('id', 'Id is empty, or format is invalid', self::MESSAGE_ERROR);
				$valid = false;
			}

			if (!RiverBasinQueries::checkUnique(['id' => $data['id']], $editedRecord ? $editedRecord['id'] : null)) {
				$this->addFieldMessage('id', 'Id must be unique', self::MESSAGE_ERROR);
				$valid = false;
			}
		}

		if (!validate($data, 'name', '', true)) {
			$this->addFieldMessage('name', 'River basin name is empty, or format is invalid', self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!RiverBasinQueries::checkUnique(['name' => $data['name']], $editedRecord ? $editedRecord['id'] : null, 'id')) {
			$this->addFieldMessage('name', 'Name must be unique', self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


}
