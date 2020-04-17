<?php

namespace Environet\Sys\Admin\Pages\MeasurementAccessRule;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\GroupQueries;
use Environet\Sys\General\Db\MeasurementAccessRuleQueries;

/**
 * Class DataAccessRuleCrud
 *
 * Handles CRUD operations for data access rules.
 *
 * @package Environet\Sys\Admin\Pages\DataProvider
 * @author  SRG Group <dev@srg.hu>
 */
class MeasurementAccessRuleCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = MeasurementAccessRuleQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/measurement-access-rule/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/measurement-access-rule/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/measurement-access-rule/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/measurement-access-rules';

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'Measurement access rule successfully added';


	/**
	 * @inheritDoc
	 */
	protected function formContext(): array {
		return [
			'groups' => GroupQueries::getOptionList(),
		];
	}


}
