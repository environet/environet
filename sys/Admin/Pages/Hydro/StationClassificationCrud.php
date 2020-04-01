<?php

namespace Environet\Sys\Admin\Pages\Hydro;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\HydroStationClassificationQueries;
use Environet\Sys\General\Exceptions\HttpNotFoundException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class StationClassificationCrud
 *
 * @package Environet\Sys\Admin\Pages\Hydro
 * @author  Mate Kovacs <mate.kovacs@srg.hu>
 */
class StationClassificationCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = HydroStationClassificationQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/hydro/stationclassification/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/hydro/stationclassification/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/hydro/stationclassification/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/hydro/station-classifications';

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'Station classification successfully saved';


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
