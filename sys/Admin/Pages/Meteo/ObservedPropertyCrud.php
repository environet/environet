<?php

namespace Environet\Sys\Admin\Pages\Meteo;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\MeteoObservedPropertyQueries;
use Environet\Sys\General\Exceptions\HttpNotFoundException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class ObservedPropertyCrud
 *
 * @package Environet\Sys\Admin\Pages\Meteo
 * @author  Mate Kovacs <mate.kovacs@srg.hu>
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
	protected $successAddMessage = 'Observed property successfully saved';


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data): bool {
		$valid = true;

		if (!validate($data, 'symbol', REGEX_NAME, true)) {
			$this->addMessage('Observed property symbol is empty, or format is invalid', self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!validate($data, 'description', REGEX_NAME, true)) {
			$this->addMessage('Observed property description is empty, or format is invalid', self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


}
