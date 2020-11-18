<?php

namespace Environet\Sys\Admin\Pages\Meteo;

use Environet\Sys\General\Db\MeteoMonitoringPointQueries;
use Environet\Sys\General\Db\MeteoObservedPropertyQueries;
use Environet\Sys\General\Db\MeteoStationClassificationQueries;
use Environet\Sys\General\Db\OperatorQueries;
use Environet\Sys\Admin\Pages\MonitoringPoint\MonitoringPointCrud as MonitoringPointCrudBase;

/**
 * Class MonitoringPointCrud
 *
 * Handles CRUD operations for meteopoint monitoring points.
 *
 * @package Environet\Sys\Admin\Pages\Meteo
 * @author  SRG Group <dev@srg.hu>
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
	protected $listPagePath            = '/admin/meteo/monitoring-points';

	/**
	 * @var string
	 */
	protected $readOwnPermissionName   = 'admin.meteo.monitoringpoints.readown';

	/**
	 * @var string
	 */
	protected $updateOwnPermissionName = 'admin.meteo.monitoringpoints.updateown';


	/**
	 * @inheritDoc
	 */
	public function getObservedPropertyQueriesClass(): string {
		return MeteoObservedPropertyQueries::class;
	}


	/**
	 * @inheritDoc
	 */
	public function getGlobalIdName(): string {
		return 'ncd_pst';
	}


	/**
	 * @inheritDoc
	 *
	 * @return array
	 */
	protected function formContext(): array {
		return [
			'classifications'    => MeteoStationClassificationQueries::getOptionList('value'),
			'operators'          => OperatorQueries::getOptionList('name'),
			'observedProperties' => MeteoObservedPropertyQueries::getOptionList('symbol')
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
				'ncd_pst' => 'NCD PST [text]',
			],
			$columns,
			[
				'classification' => ['title' => 'Station classification ID [ID]', 'outField' => 'meteostation_classificationid'],
				'altitude'       => 'Altitue [number]',
			]
		);
	}


	/**
	 * @return array
	 */
	protected function getCsvEnums(): array {
		return [
			['title' => 'Station classifications', 'options' => MeteoStationClassificationQueries::getOptionList('value')]
		];
	}


}
