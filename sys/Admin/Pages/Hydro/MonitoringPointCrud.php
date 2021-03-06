<?php

namespace Environet\Sys\Admin\Pages\Hydro;

use Environet\Sys\General\Db\HydroMonitoringPointQueries;
use Environet\Sys\General\Db\HydroObservedPropertyQueries;
use Environet\Sys\General\Db\HydroStationClassificationQueries;
use Environet\Sys\General\Db\OperatorQueries;
use Environet\Sys\General\Db\RiverbankQueries;
use Environet\Sys\General\Db\UserQueries;
use Environet\Sys\General\Db\WaterbodyQueries;
use Environet\Sys\Admin\Pages\MonitoringPoint\MonitoringPointCrud as MonitoringPointCrudBase;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class MonitoringPointCrud
 *
 * Handles CRUD operations for hydropoint monitoring points.
 *
 * @package Environet\Sys\Admin\Pages\Hydro
 * @author  SRG Group <dev@srg.hu>
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

	/**
	 * @var string
	 */
	protected $readOwnPermissionName = 'admin.hydro.monitoringpoints.readown';

	/**
	 * @var string
	 */
	protected $createOwnPermissionName = 'admin.hydro.monitoringpoints.createown';

	/**
	 * @var string
	 */
	protected $updateOwnPermissionName = 'admin.hydro.monitoringpoints.updateown';


	/**
	 * @inheritDoc
	 */
	public function getObservedPropertyQueriesClass(): string {
		return HydroObservedPropertyQueries::class;
	}


	/**
	 * @inheritDoc
	 */
	public function getGlobalIdName(): string {
		return 'ncd_wgst';
	}


	/**
	 * @inheritDoc
	 *
	 * @return array
	 * @throws QueryException
	 */
	protected function formContext(): array {
		return [
			'classifications'    => HydroStationClassificationQueries::getOptionList('value'),
			'operators'          => $this->getOperatorList(),
			'riverbanks'         => RiverbankQueries::getOptionList('value'),
			'waterbodies'        => WaterbodyQueries::getOptionList('cname', 'european_river_code'),
			'observedProperties' => HydroObservedPropertyQueries::getOptionList('symbol'),
		];
	}


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data): bool {
		$valid = true;

		if (!validate($data, 'name', REGEX_ALPHANUMERIC, true)) {
			$this->addMessage('Monitoring point name is empty, or format is invalid', self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!empty($data['country']) && strlen($data['country']) > 2) {
			$this->addMessage('County field expects a two letter country code', self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


	/**
	 * @return array
	 */
	public function getCsvColumns(): array {
		$columns = parent::getCsvColumns();

		return array_merge(
			[
				'ncd_wgst' => 'NCD WGST [text]'
			],
			$columns,
			[
				'classification' => ['title' => 'Station classification ID [ID]', 'outField' => 'station_classificationid'],
				'river_kilometer' => 'River kilometer [number]',
				'catchment_area'  => 'Catchment area [number]',
				'gauge_zero'      => 'Gauge zero [number]',
			]
		);
	}


	/**
	 * @return array
	 */
	protected function getCsvEnums(): array {
		return [
			['title' => 'Station classifications', 'options' => HydroStationClassificationQueries::getOptionList('value')],
			['title' => 'Riverbanks', 'options' => RiverbankQueries::getOptionList('value')],
			['title' => 'Waterbodies', 'options' => WaterbodyQueries::getOptionList('cname', 'european_river_code')]
		];
	}


}
