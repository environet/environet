<?php

namespace Environet\Sys\Admin\Pages\Meteo;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\MeteoObservedPropertyQueries;

/**
 * Class ObservedPropertyCrud
 *
 * Handles CRUD operations for meteopoint observed properties.
 *
 * @package Environet\Sys\Admin\Pages\Meteo
 * @author  SRG Group <dev@srg.hu>
 */
class ObservedPropertyCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = MeteoObservedPropertyQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/meteo/observed-property/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/meteo/observed-property/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/meteo/observed-property/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/meteo/observed-properties';

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
		return $plural ? 'meteo observed properties' : 'meteo observed property';
	}


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data, ?array $editedRecord = null): bool {
		$valid = true;

		if (!validate($data, 'symbol', REGEX_ALPHANUMERIC, true)) {
			$this->addMessage('Observed property symbol is empty, or format is invalid', self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!MeteoObservedPropertyQueries::checkUnique(['symbol' => $data['symbol'], 'type' => $data['type']], $editedRecord ? $editedRecord['id'] : null)) {
			$this->addFieldMessage('symbol', sprintf('Symbol must be unique with type %s', observedPropertyTypeOptions()[$data['type']] ?? null), self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


}
