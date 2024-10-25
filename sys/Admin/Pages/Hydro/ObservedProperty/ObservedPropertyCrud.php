<?php

namespace Environet\Sys\Admin\Pages\Hydro\ObservedProperty;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\HydroObservedPropertyQueries;

/**
 * Class ObservedPropertyCrud
 *
 * Handles CRUD operations for hydropoint observed properties.
 *
 * @package Environet\Sys\Admin\Pages\Hydro\ObservedProperty
 * @author  SRG Group <dev@srg.hu>
 */
class ObservedPropertyCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = HydroObservedPropertyQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/hydro/observed-property/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = 'hydro/observed-property/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = 'hydro/observed-property/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/hydro/observed-properties';

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'Observed property successfully added';

	/**
	 * @inheritdoc
	 */
	protected $successEditMessage = 'Observed property successfully saved';


	/**
	 * @param bool $plural
	 *
	 * @return string
	 */
	protected function getEntityName(bool $plural = false): string {
		return $plural ? 'hydro observed properties' : 'hydro observed property';
	}


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data, ?array $editedRecord = null): bool {
		$valid = true;

		if (!validate($data, 'symbol', REGEX_ALPHANUMERIC, true)) {
			$this->addFieldMessage('symbol', 'Observed property symbol is empty, or format is invalid', self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!HydroObservedPropertyQueries::checkUnique(['symbol' => $data['symbol']], $editedRecord ? $editedRecord['id'] : null)) {
			$this->addFieldMessage('symbol', 'Symbol must be unique', self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


}
