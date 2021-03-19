<?php

namespace Environet\Sys\Admin\Pages\Meteo;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\MeteoStationClassificationQueries;

/**
 * Class StationClassificationCrud
 *
 * Handles CRUD operations for meteopoint station classifications.
 *
 * @package Environet\Sys\Admin\Pages\Meteo
 * @author  SRG Group <dev@srg.hu>
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
	 * @param bool $plural
	 *
	 * @return string
	 */
	protected function getEntityName(bool $plural = false): string {
		return $plural ? 'meteo station classifications' : 'meteo station classification';
	}


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data, ?array $editedRecord = null): bool {
		$valid = true;

		if (!validate($data, 'value', REGEX_ALPHANUMERIC, true)) {
			$this->addFieldMessage('value', 'Station classification value is empty, or format is invalid', self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!MeteoStationClassificationQueries::checkUnique(['value' => $data['value']], $editedRecord ? $editedRecord['id'] : null)) {
			$this->addFieldMessage('value', 'Value must be unique', self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


}
