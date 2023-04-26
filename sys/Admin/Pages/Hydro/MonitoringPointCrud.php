<?php

namespace Environet\Sys\Admin\Pages\Hydro;

use Environet\Sys\General\Db\Connection;
use Environet\Sys\General\Db\HydroMonitoringPointQueries;
use Environet\Sys\General\Db\HydroObservedPropertyQueries;
use Environet\Sys\General\Db\HydroStationClassificationQueries;
use Environet\Sys\General\Db\Query\Insert;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\Query\Update;
use Environet\Sys\General\Db\RiverbankQueries;
use Environet\Sys\General\Db\RiverBasinQueries;
use Environet\Sys\General\Db\RiverQueries;
use Environet\Sys\Admin\Pages\MonitoringPoint\MonitoringPointCrud as MonitoringPointCrudBase;
use Environet\Sys\General\Db\WarningLevelQueries;
use Environet\Sys\General\Exceptions\HttpBadRequestException;
use Environet\Sys\General\Exceptions\HttpNotFoundException;
use Environet\Sys\General\Exceptions\PermissionException;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;
use Throwable;

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
	 * @var
	 */
	protected $warningLevelsByPoints = null;


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
			'rivers'             => RiverQueries::getOptionList('cname', 'eucd_riv'),
			'riverBasins'        => RiverBasinQueries::getOptionList(),
			'observedProperties' => HydroObservedPropertyQueries::getOptionList('symbol'),
		];
	}


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data, ?array $editedRecord = null): bool {
		$valid = true;

		if (!validate($data, 'name', REGEX_ALPHANUMERIC, true)) {
			$this->addFieldMessage('name', 'Monitoring point name is empty, or format is invalid', self::MESSAGE_ERROR);
			$valid = false;
		}
		if (!validate($data, 'country', null, true)) {
			$this->addFieldMessage('country', 'Country is required', self::MESSAGE_ERROR);
			$valid = false;
		}
		if (!validate($data, 'ncd_wgst', null, true)) {
			$this->addFieldMessage('ncd_wgst', 'NCD WGST is required', self::MESSAGE_ERROR);
			$valid = false;
		}
		if (!validate($data, 'operator', null, true)) {
			$this->addFieldMessage('operator', 'Operator is required', self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!empty($data['country']) && !preg_match('/^[A-Z]{2}$/', $data['country'])) {
			$this->addMessage('County field expects a valid country code (2 capital letters)', self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!HydroMonitoringPointQueries::checkUnique(['ncd_wgst' => $data['ncd_wgst'], 'operatorid' => $data['operator']], $editedRecord ? $editedRecord['id'] : null)) {
			$this->addFieldMessage('ncd_wgst', sprintf('NCD WGST must be unique for operator #%d', $data['operator']), self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


	/**
	 * Function to handle warning-levels edit method.
	 *
	 * @return Response
	 * @throws HttpBadRequestException
	 * @throws HttpNotFoundException
	 * @throws PermissionException
	 * @throws QueryException
	 * @throws RenderException
	 */
	public function warningLevels(): Response {
		$mpointId = $this->getIdParam();
		$record = $this->getRecordById($mpointId);

		if (!$this->userCanEdit($mpointId)) {
			throw new PermissionException("You don't have permission to edit record with id: '$mpointId'");
		}

		$existingDataByKey = $this->getExistingWarningLevelData($mpointId);

		//Build form context
		$pageTitle = sprintf('Edit threshold levels for monitoring point: %s', $record['name']);
		$context = array_merge([
			'record'    => $record,
			'listPage'  => $this->getListPageLinkWithState(),
			'pageTitle' => $pageTitle,
			'data'      => array_combine(array_keys($existingDataByKey), array_column($existingDataByKey, 'value'))
		], $this->formContext(), [
			'warningLevels'      => WarningLevelQueries::getOptionListForOperator($record['operatorid']),
			'observedProperties' => HydroObservedPropertyQueries::getRealTimeOptionList(),
		]);

		if ($this->request->isPost()) {
			//Save thresholds to database
			$postData = $this->request->getCleanData();

			if (!$this->checkCsrf()) {
				// if the csrf token isn't valid
				throw new HttpBadRequestException('CSRF validation failed');
			}

			try {
				$connection = Connection::getInstance();
				$connection->runQuery("BEGIN TRANSACTION;", []);

				$this->saveWarningLevels($mpointId, $postData, $existingDataByKey);

				$connection->runQuery("COMMIT TRANSACTION;", []);
				$this->addMessage('Threshold levels have been successfully saved', self::MESSAGE_SUCCESS);

				return $this->redirect($this->getListPageLinkWithState());
			} catch (Throwable $e) {
				$connection->runQuery("ROLLBACK TRANSACTION;", []);
				$this->addMessage('Can\'t save form data', self::MESSAGE_ERROR);

				return $this->render('/hydro/monitoringpoint/form-warning-levels.phtml', $context);
			}
		}

		return $this->render('/hydro/monitoringpoint/form-warning-levels.phtml', $context);
	}


	/**
	 * @return array
	 */
	public function getCsvColumns(): array {
		$columns = parent::getCsvColumns();

		$observedProperties = HydroObservedPropertyQueries::getOptionList('symbol');
		$propertyWarningLevelCols = [];
		foreach ($observedProperties as $symbol) {
			$propertyWarningLevelCols['warning_level_' . $symbol] = "Threshold levels [$symbol] - separated with comma";
		}

		return array_merge(
			[
				'ncd_wgst' => 'NCD WGST [text]'
			],
			$columns,
			[
				'classification'  => ['title' => 'Station classification ID [ID]', 'outField' => 'station_classificationid'],
				'river_kilometer' => 'River kilometer [number]',
				'catchment_area'  => 'Catchment area [number]',
				'gauge_zero'      => 'Gauge zero [number]',
			],
			$propertyWarningLevelCols
		);
	}


	/**
	 * @return array
	 */
	protected function getCsvEnums(): array {
		return [
			['title' => 'Station classifications', 'options' => HydroStationClassificationQueries::getOptionList('value')],
			['title' => 'Riverbanks', 'options' => RiverbankQueries::getOptionList('value')],
			['title' => 'Rivers', 'options' => RiverQueries::getOptionList('cname', 'eucd_riv')],
			['title' => 'Sub-basins', 'options' => RiverBasinQueries::getOptionList()]
		];
	}


	/**
	 * @param $field
	 */
	protected function getCsvField($mpointId, $field) {
		if (preg_match('/^warning_level_(.*)$/i', $field, $match)) {
			if (isset($this->getWarningLevelsByPoints()[$mpointId][$match[1]])) {
				$value = [];
				foreach ($this->getWarningLevelsByPoints()[$mpointId][$match[1]] as $wl) {
					$value[] = floatval($wl['value']);
				}

				return implode(',', $value);
			}

			return '';
		}

		return false;
	}


	/**
	 * @param $data
	 * @param $mpointId
	 */
	protected function csvUploadAfterSave($data, $mpointId) {
		$operatorId = $data['operator'];
		$observedProperties = array_flip(HydroObservedPropertyQueries::getRealTimeOptionList());
		$warningLevelIds = array_keys(WarningLevelQueries::getOptionListForOperator($operatorId));
		$saveWarningLevels = [];
		foreach ($data as $key => $value) {
			if ($value === '') {
				continue;
			}
			if (preg_match('/^warning_level_(.*)$/i', $key, $match)) {
				if (array_key_exists($match[1], $observedProperties)) {
					$warningLevelThresholds = explode(',', $value);
					foreach ($warningLevelThresholds as $key => $threshold) {
						if (!isset($warningLevelIds[$key])) {
							continue;
						}
						$saveWarningLevels[$observedProperties[$match[1]] . '_' . $warningLevelIds[$key]] = floatval($threshold);
					}
				}
			}
		}
		if (!empty($saveWarningLevels)) {
			$this->saveWarningLevels($mpointId, $saveWarningLevels);
		}
	}


	/**
	 * @return array
	 * @throws QueryException
	 */
	protected function getWarningLevelsByPoints(): array {
		if (is_null($this->warningLevelsByPoints)) {
			$this->warningLevelsByPoints = [];
			$wls = (new Select())
				->from('warning_level_hydropoint wlh')
				->join('warning_levels wl', 'wl.id = wlh.warning_levelid')
				->join('warning_level_groups wlg', 'wlg.id = wl.warning_level_groupid')
				->join('hydro_observed_property hop', 'hop.id = wlh.observed_propertyid')
				->where('hop.type = :propertyType')
				->addParameter('propertyType', PROPERTY_TYPE_REALTIME)
				->select([
					'wl.short_description',
					'wl.long_description',
					'wl.color',
					'wl.is_inclusive',
					'wlg.name as group',
					'hop.symbol',
					'wlh.value',
					'wlh.mpointid '
				])
				->orderBy('wlg.id', 'ASC')
				->run();
			foreach ($wls as $wl) {
				if (!isset($this->warningLevelsByPoints[$wl['mpointid']][$wl['symbol']])) {
					$this->warningLevelsByPoints[$wl['mpointid']][$wl['symbol']] = [];
				}
				$this->warningLevelsByPoints[$wl['mpointid']][$wl['symbol']][] = $wl;
			}
		}

		return $this->warningLevelsByPoints;
	}


	/**
	 * Get all existing data for monitorin point, and organize under keys (property_warninglevel)
	 *
	 * @param $mpointId
	 *
	 * @return array|bool|int|null
	 * @throws QueryException
	 */
	protected function getExistingWarningLevelData($mpointId) {
		$existingData = (new Select())
			->select('*')
			->from('warning_level_hydropoint')
			->where('mpointid = :mpointId')
			->addParameter(':mpointId', $mpointId)
			->run();
		$existingDataByKey = [];
		foreach ($existingData as $datum) {
			$datum['value'] = floatval($datum['value']);
			$existingDataByKey[$datum['observed_propertyid'] . '_' . $datum['warning_levelid']] = $datum;
		}

		return $existingDataByKey;
	}


	/**
	 * @param int        $mpointId
	 * @param array      $data
	 * @param array|null $existingDataByKey
	 *
	 * @throws QueryException
	 */
	protected function saveWarningLevels(int $mpointId, array $data, array $existingDataByKey = null) {
		$existingDataByKey = $existingDataByKey ?: $this->getExistingWarningLevelData($mpointId);

		foreach ($data as $key => $value) {
			if ($value === '' || $key === '__csrf') {
				continue;
			}
			//Convert to float, and split key
			$value = floatval($value);
			$observedPropertyId = explode('_', $key)[0];
			$warningLevelId = explode('_', $key)[1];
			if (!array_key_exists($key, $existingDataByKey)) {
				//Threshold not saved yet, insert into databas
				(new Insert())->table('warning_level_hydropoint')->addSingleData([
					'observed_propertyid' => $observedPropertyId,
					'warning_levelid'     => $warningLevelId,
					'mpointid'            => $mpointId,
					'value'               => $value
				])->run(Query::RETURN_BOOL);
			} elseif ($value !== $existingDataByKey[$key]['value']) {
				//Existing, and value updated, save it
				(new Update())
					->table('warning_level_hydropoint')
					->updateData(['value' => $value])
					->where('warning_level_hydropoint.observed_propertyid = :observedPropertyId')
					->where('warning_level_hydropoint.warning_levelid = :warningLevelId')
					->where('warning_level_hydropoint.mpointid = :mpointId')
					->addParameters([
						'mpointId'           => $mpointId,
						'warningLevelId'     => $warningLevelId,
						'observedPropertyId' => $observedPropertyId,
					])
					->run(Query::RETURN_BOOL);
			}
		}
	}


}
