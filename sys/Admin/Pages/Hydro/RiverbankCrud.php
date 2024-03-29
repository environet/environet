<?php

namespace Environet\Sys\Admin\Pages\Hydro;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\RiverbankQueries;


/**
 * Class StationClassificationCrud
 *
 * Handles CRUD operations for hydropoint station classifications.
 *
 * @package Environet\Sys\Admin\Pages\Hydro
 * @author  SRG Group <dev@srg.hu>
 */
class RiverbankCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = RiverbankQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/hydro/riverbank/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/hydro/riverbank/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/hydro/riverbank/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/hydro/riverbanks';

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'Riverbank successfully added';

	/**
	 * @inheritdoc
	 */
	protected $successEditMessage = 'Riverbank successfully saved';


	/**
	 * @param bool $plural
	 *
	 * @return string
	 */
	protected function getEntityName(bool $plural = false): string {
		return $plural ? 'riverbanks' : 'riverbank';
	}


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data, ?array $editedRecord = null): bool {
		$valid = true;

		if (!validate($data, 'value', REGEX_ALPHANUMERIC, true)) {
			$this->addFieldMessage('value', 'Riverbank value is empty, or format is invalid', self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!RiverbankQueries::checkUnique(['value' => $data['value']], $editedRecord ? $editedRecord['id'] : null)) {
			$this->addFieldMessage('value', 'Value must be unique', self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


}
