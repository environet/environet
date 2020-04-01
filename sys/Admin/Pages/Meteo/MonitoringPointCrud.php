<?php

namespace Environet\Sys\Admin\Pages\Meteo;

use Environet\Sys\General\Db\MeteoMonitoringPointQueries;
use Environet\Sys\General\Db\MeteoObservedPropertyQueries;
use Environet\Sys\General\Db\MeteoStationClassificationQueries;
use Environet\Sys\General\Db\OperatorQueries;
use Environet\Sys\Admin\Pages\MonitoringPoint\MonitoringPointCrud as MonitoringPointCrudBase;

/**
 * Class MonitoringPointCrud
 * @package Environet\Sys\Admin\Pages\Hydro
 */
class MonitoringPointCrud extends MonitoringPointCrudBase {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = MeteoMonitoringPointQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/meteo/monitoringpoint/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/meteo/monitoringpoint/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/meteo/monitoringpoint/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/meteo/monitoring-points';

	protected $observedPropertyQueriesClass = MeteoObservedPropertyQueries::class;
	protected $observedPropertiesCsvColumn = 4;
	protected $globalIdName = 'ncd_pst';

	protected $csvColumnMappings = [
		'name' => 0,
		'eucd_pst' => 1,
		'ncd_pst' => 2,
		'country' => 3,
	];


	/**
	 * @inheritDoc
	 *
	 * @return array
	 */
	protected function formContext(): array {
		return [
			'classifications' => MeteoStationClassificationQueries::getOptionList('value'),
			'operators' => OperatorQueries::getOptionList('name'),
			'observedProperties' => MeteoObservedPropertyQueries::getOptionList('symbol')
		];
	}


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data): bool {
		$valid = true;

		if (!validate($data, 'name', REGEX_NAME, true)) {
			$this->addMessage('Monitoring point name is empty, or format is invalid', self::MESSAGE_ERROR);
			$valid = false;
		}

		/*if (!$data['classification']) {
			$this->addMessage(__('Classification is required'), self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!$data['operator']) {
			$this->addMessage(__('Operator is required'), self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!$data['observedProperties']) {
			$this->addMessage(__('Observed property is required'), self::MESSAGE_ERROR);
			$valid = false;
		}*/

		return $valid;
	}


}
