<?php

namespace Environet\Sys\Admin\Pages\Hydro;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\HydroStationClassificationQueries;

/**
 * Class StationClassificationCrud
 *
 * Handles CRUD operations for hydropoint station classifications.
 *
 * @package Environet\Sys\Admin\Pages\Hydro
 * @author  SRG Group <dev@srg.hu>
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
	 * @param bool $plural
	 *
	 * @return string
	 */
	protected function getEntityName(bool $plural = false): string {
		return $plural ? 'hydro station classifications' : 'hydro station classification';
	}


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data): bool {
		$valid = true;

		if (!validate($data, 'value', REGEX_ALPHANUMERIC, true)) {
			$this->addMessage('Station classification value is empty, or format is invalid', self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


}
