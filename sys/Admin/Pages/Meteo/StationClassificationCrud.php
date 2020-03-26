<?php

namespace Environet\Sys\Admin\Pages\Meteo;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\MeteoStationClassificationQueries;
use Environet\Sys\General\Exceptions\HttpNotFoundException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class StationClassificationCrud
 *
 * @package Environet\Sys\Admin\Pages\Meteo
 * @author  Mate Kovacs <mate.kovacs@srg.hu>
 */
class StationClassificationCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = MeteoStationClassificationQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/meteo/stationclassification/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/meteo/stationclassification/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/meteo/stationclassification/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/meteo/station-classifications';

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'Station classification successfully saved';


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
	 * @throws HttpNotFoundException
	 */
	public function show(): Response {
		return $this->renderShowPage();
	}


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data): bool {
		$valid = true;

		if (!validate($data, 'value', REGEX_NAME, true)) {
			$this->addMessage('Station classification value is empty, or format is invalid', self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


}
