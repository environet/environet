<?php

namespace Environet\Sys\Admin\Pages\Hydro\Waterbody;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\WaterbodyQueries;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class WaterbodyCrud
 *
 * Handles CRUD operations for hydropoint waterbodies.
 *
 * @package Environet\Sys\Admin\Pages\Hydro\Waterbody
 * @author  SRG Group <dev@srg.hu>
 */
class WaterbodyCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = WaterbodyQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/hydro/waterbody/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/hydro/waterbody/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/hydro/waterbody/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/hydro/waterbodies';

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'Waterbody successfully saved';


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
	 * Show page action.
	 *
	 * @return Response
	 * @throws RenderException
	 */
	public function show(): Response {
		// Get id from url and get record by id
		$id = $this->request->getQueryParam('id');
		if (is_null($id)) {
			// if id doesn't exist, return 404
			return httpErrorPage(404);
		}

		$record = $this->queriesClass::getById($id, 'european_river_code');
		if (is_null($record)) {
			// if the requested record doesn't exist, return 404
			return httpErrorPage(404);
		}

		return $this->render($this->showTemplate, compact('record'));
	}


	/**
	 * Common function to handle edit method.
	 *
	 * @return Response
	 * @throws RenderException
	 */
	public function edit(): Response {
		$id = $this->request->getQueryParam('id');
		if (is_null($id)) {
			// if id doesn't exist, return 404
			return httpErrorPage(404);
		}
		$record = $this->queriesClass::getById($id, 'european_river_code');
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
	 */
	protected function handleFormPost($id = null, $record = null): Response {
		$postData = $this->request->getCleanData();

		if (!$this->validateData($postData)) {
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
				'european_river_code' => $postData['european_river_code'] ?? null,
			];
		} else {
			$postData = [
				'cname' => $postData['cname'] ?? null
			];
		}

		//Data is valid, save it, add success message, and redirect to index page
		try {
			$this->queriesClass::save($postData, $id, 'european_river_code');
			$this->addMessage($this->successAddMessage, self::MESSAGE_SUCCESS);

			return $this->redirect($this->listPagePath);
		} catch (QueryException $e) {
			$this->addMessage('Error while saving data: ' . $e->getMessage(), self::MESSAGE_ERROR);

			return $this->renderForm();
		}
	}


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data): bool {
		$id = $this->request->getQueryParam('id');
		$valid = true;

		if (is_null($id)) {
			if (!validate($data, 'european_river_code', REGEX_RIVERCODE, true)) {
				$this->addMessage('Waterbody european river code is empty, or format is invalid', self::MESSAGE_ERROR);
				$valid = false;
			}
		}

		if (!validate($data, 'cname', '', true)) {
			$this->addMessage('Waterbody cname is empty, or format is invalid', self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


}
