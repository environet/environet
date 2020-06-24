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
	protected $successAddMessage = 'Riverbank successfully saved';


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data): bool {
		$valid = true;

		if (!validate($data, 'value', REGEX_ALPHANUMERIC, true)) {
			$this->addMessage('Riverbank value is empty, or format is invalid', self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


}
