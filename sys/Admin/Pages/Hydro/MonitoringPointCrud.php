<?php

namespace Environet\Sys\Admin\Pages\Hydro;

use Environet\Sys\General\Db\HydroMonitoringPointQueries;
use Environet\Sys\General\Db\HydroObservedPropertyQueries;
use Environet\Sys\General\Db\HydroStationClassificationQueries;
use Environet\Sys\General\Db\OperatorQueries;
use Environet\Sys\General\Db\RiverbankQueries;
use Environet\Sys\General\Db\WaterbodyQueries;
use Environet\Sys\Admin\Pages\MonitoringPoint\MonitoringPointCrud as MonitoringPointCrudBase;

/**
 * Class MonitoringPointCrud
 * @package Environet\Sys\Admin\Pages\Hydro
 */
class MonitoringPointCrud extends MonitoringPointCrudBase {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = HydroMonitoringPointQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/hydro/monitoringpoint/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/hydro/monitoringpoint/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/hydro/monitoringpoint/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/hydro/monitoring-points';

	protected $observedPropertyQueriesClass = HydroObservedPropertyQueries::class;
	protected $observedPropertiesCsvColumn = 4;
	protected $globalIdName = 'ncd_wgst';

	protected $csvColumnMappings = [
		'name' => 0,
		'eucd_wgst' => 1,
		'ncd_wgst' => 2,
		'country' => 3,
	];


	/**
	 * @inheritDoc
	 *
	 * @return array
	 */
	protected function formContext(): array {
		return [
			'classifications' => HydroStationClassificationQueries::getOptionList('value'),
			'operators' => OperatorQueries::getOptionList('name'),
			'riverbanks' => RiverbankQueries::getOptionList('value'),
			'waterbodies' => WaterbodyQueries::getOptionList('cname', 'european_river_code'),
			'observedProperties' => HydroObservedPropertyQueries::getOptionList('symbol'),
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
		}*/

		/*if (!$data['operator']) {
			$this->addMessage(__('Operator is required'), self::MESSAGE_ERROR);
			$valid = false;
		}*/

		/*if (!$data['riverbank']) {
			$this->addMessage(__('Riverbank is required'), self::MESSAGE_ERROR);
			$valid = false;
		}*/

		/*if (!$data['waterbody']) {
			$this->addMessage(__('Waterbody is required'), self::MESSAGE_ERROR);
			$valid = false;
		}*/

		/*if (!$data['observedProperties']) {
			$this->addMessage(__('Observed property is required'), self::MESSAGE_ERROR);
			$valid = false;
		}*/

		return $valid;
	}


}
