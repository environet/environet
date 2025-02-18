<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\Db\Query\Select;

/**
 * Class CalculationConfigQueries
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class CalculationConfigQueries extends BaseQueries {

	public static $tableName = 'calculation_configs';

	const INTERVAL_HOUR  = 'hourly';
	const INTERVAL_DAY   = 'daily';
	const INTERVAL_WEEK  = 'weekly';
	const INTERVAL_MONTH = 'monthly';
	const INTERVAL_YEAR  = 'yearly';

	public static array $intervals = [
		self::INTERVAL_HOUR,
		self::INTERVAL_DAY,
		self::INTERVAL_WEEK,
		self::INTERVAL_MONTH,
		self::INTERVAL_YEAR,
	];


	public static function getActiveCalculations(): array {
		return (new Select())->select(static::$tableName . '.*')
			->where('is_active = true')
			->from(static::$tableName)
			->run();
	}


	/**
	 * @param array $data
	 *
	 * @return array
	 */
	public static function prepareData(array $data): array {
		return [
			'name'              => $data['name'],
			'operatorid'        => $data['operatorid'],
			'mpointid'          => $data['mpointid'] ?? null,
			'source_interval'   => $data['source_interval'],
			'start_time'        => $data['start_time'],
			'source_propertyid' => $data['source_propertyid'],
			'target_propertyid' => $data['target_propertyid'],
			'target_interval'   => $data['target_interval'],
		];
	}


	/**
	 * @inheritDoc
	 */
	public static function getInsertEventType(): string {
		return 'calculation_config_add';
	}


	/**
	 * @inheritDoc
	 */
	public static function getDeleteEventType(): string {
		return 'calculation_config_delete';
	}


	/**
	 * @inheritDoc
	 */
	public static function getUpdateEventType(): string {
		return 'calculation_config_update';
	}


}
