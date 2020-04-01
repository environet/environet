<?php

namespace Environet\Sys\Admin\Pages\Hydro;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\HydroMonitoringPointQueries;
use Environet\Sys\General\Db\HydroObservedPropertyQueries;
use Environet\Sys\General\Db\HydroStationClassificationQueries;
use Environet\Sys\General\Db\OperatorQueries;
use Environet\Sys\General\Db\RiverbankQueries;
use Environet\Sys\General\Db\WaterbodyQueries;
use Environet\Sys\General\Exceptions\HttpNotFoundException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class MonitoringPointCrud
 * @package Environet\Sys\Admin\Pages\Hydro
 */
class MonitoringPointCrud extends CrudPage {

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

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'Monitoring point successfully saved';


	public function csvUpload(): string {
		if ($this->request->isPost()) {
			$this->queriesClass::save([
				'name' => 'zdzd',
				'eucd_wgst' => 'sghsghd',
				'ncd_wgst' => 'sdfshsdfsxdfghgs',
				'country' => 'HU',
			]);
		}

		return $this->renderListPage();
	}


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

		/*if (!$data['waterbodyeuropean_river_code']) {
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
