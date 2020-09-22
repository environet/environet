<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\Db\Query\Insert;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\Query\Update;
use Environet\Sys\General\EventLogger;

/**
 * Class DataAccessRuleQueries
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class MeasurementAccessRuleQueries extends BaseQueries {

	/**
	 * @inheritDoc
	 */
	public static $tableName = 'measurement_access_rules';


	/**
	 * @inheritDoc
	 */
	public static function getById($id, string $primaryKey = 'id'): ?array {
		$record = parent::getById($id, $primaryKey);

		$groupConnections = (new Select())
			->select(['group_id', 'interval'])
			->from('group_measurement_access_rules')
			->where('measurement_access_rule_id = :id')
			->addParameter(':id', $id)
			->run();


		if (count($groupConnections)) {
			$record['groups'] = array_column($groupConnections, 'group_id');
			$record['interval'] = array_column($groupConnections, 'interval')[0];
		}

		return $record;
	}


	public static function prepareData(array $data): array {
		if (is_array(($monitoringPointSelector = $data['monitoringpoint_selector'] ?? null))) {
			$monitoringPointSelector = array_search('*', $monitoringPointSelector) !== false ? '*' : implode(',', array_unique(array_filter($monitoringPointSelector)));
		}
		if (is_array(($observedPropertySelector = $data['observed_property_selector'] ?? null))) {
			$observedPropertySelector = array_search('*', $observedPropertySelector) !== false ? '*' : implode(',', array_unique(array_filter($observedPropertySelector)));
		}
		return [
			'operator_id' => $data['operator'] ?? null,
			'monitoringpoint_selector' => $monitoringPointSelector,
			'observed_property_selector' => $observedPropertySelector,
		];
	}


	/**
	 * @inheritDoc
	 */
	public static function save(array $data, $id = null, string $primaryKey = 'id') {
		$dataToSave = static::prepareData($data);

		if ($id) {
			EventLogger::log(static::getUpdateEventType(), array_merge($dataToSave, [
				'id' => $id
			]));

			(new Update())
				->table(static::$tableName)
				->updateData($dataToSave)
				->where(static::$tableName . ".$primaryKey = :id")
				->addParameter(':id', $id)
				->run(Query::RETURN_BOOL);
		} else {
			$id = (new Insert())
				->table(static::$tableName)
				->addSingleData($dataToSave)
				->run();

			EventLogger::log(static::getInsertEventType(), array_merge($dataToSave, [
				'id' => $id
			]));
		}

		$years = $data['interval_years'] ?? '';
		$months = $data['interval_months'] ?? '';
		$days = $data['interval_days'] ?? '';

		$interval = 'P' . ($years ? $years . 'Y' : '') . ($months ? $months . 'M' : '') . ($days ? $days . 'D' : '');

		if (isset($data['groups'])) {
			static::saveConnections(
				$data['groups'],
				'group_measurement_access_rules',
				'group_id',
				'measurement_access_rule_id',
				$id,
				true,
				['interval' => $interval]
			);
		}
	}


	/**
	 * @inheritDoc
	 */
	public static function getInsertEventType(): string {
		return 'measurement_access_rule_add';
	}


	/**
	 * @inheritDoc
	 */
	public static function getDeleteEventType(): string {
		return 'measurement_access_rule_delete';
	}


	/**
	 * @inheritDoc
	 */
	public static function getUpdateEventType(): string {
		return 'measurement_access_rule_delete';
	}


}
