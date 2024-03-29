<?php

namespace Environet\Sys\Admin\Pages\Hydro\River;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\RiverQueries;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class RiverCrud
 *
 * Handles CRUD operations for hydropoint rivers.
 *
 * @package Environet\Sys\Admin\Pages\Hydro\River
 * @author  SRG Group <dev@srg.hu>
 */
class RiverCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = RiverQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/hydro/river/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/hydro/river/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/hydro/river/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/hydro/rivers';

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'River successfully added';

	/**
	 * @inheritdoc
	 */
	protected $successEditMessage = 'River successfully saved';


	/**
	 * @param bool $plural
	 *
	 * @return string
	 */
	protected function getEntityName(bool $plural = false): string {
		return $plural ? 'rivers' : 'river';
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

		$record = $this->queriesClass::getById($id, 'eucd_riv');
		if (is_null($record)) {
			// if the requested record doesn't exist, return 404
			return httpErrorPage(404);
		}

		//Make an alias for pageTitle
		$record['id'] = $record['eucd_riv'];

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
		$record = $this->queriesClass::getById($id, 'eucd_riv');
		if (is_null($record)) {
			// if record doesn't exist, return 404
			return httpErrorPage(404);
		}

		if ($this->request->isPost()) {
			return self::handleFormPost($id, $record);
		}

		//Make an alias for pageTitle
		$record['id'] = $record['eucd_riv'];

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
				'cname'               => $postData['cname'] ?? null,
				'eucd_riv' => $postData['eucd_riv'] ?? null,
			];
		} else {
			$postData = [
				'cname' => $postData['cname'] ?? null
			];
		}

		//Data is valid, save it, add success message, and redirect to index page
		try {
			$this->queriesClass::save($postData, $id, 'eucd_riv');
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
			if (!validate($data, 'eucd_riv', REGEX_RIVERCODE, true)) {
				$this->addFieldMessage('eucd_riv', 'EUCD RIV is empty, or format is invalid', self::MESSAGE_ERROR);
				$valid = false;
			}

			if (!RiverQueries::checkUnique(['eucd_riv' => $data['eucd_riv']], $editedRecord ? $editedRecord['id'] : null)) {
				$this->addFieldMessage('eucd_riv', 'EUCD RIV must be unique', self::MESSAGE_ERROR);
				$valid = false;
			}
		}

		if (!validate($data, 'cname', '', true)) {
			$this->addFieldMessage('cname', 'River cname is empty, or format is invalid', self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!RiverQueries::checkUnique(['cname' => $data['cname']], $editedRecord ? $editedRecord['eucd_riv'] : null, 'eucd_riv')) {
			$this->addFieldMessage('cname', 'Name must be unique', self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


}
