<?php

namespace Environet\Sys\General\Db;

use DateTime;
use Exception;

/**
 * Class DownloadLogQueries
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class DownloadLogQueries extends BaseQueries {

	/**
	 * @inheritDoc
	 */
	public static $tableName = 'download_logs';


	/**
	 * @param array $data
	 *
	 * @return array
	 */
	public static function prepareData(array $data): array {
		$sanitizeDate = function ($data) {
			if ($data) {
				try {
					$date = new DateTime(filter_var($data, FILTER_SANITIZE_STRING));

					return $date->format('Y-m-d H:i:s');
				} catch (Exception $e) {
					return null;
				}
			}

			return null;
		};

		return [
			'created_at'         => $data['created_at'],
			'user_id'            => $data['user_id'],
			'request_attributes' => !empty($data['request_attributes']) ? $data['request_attributes'] : null,
			'request_ip'         => !empty($data['request_ip']) ? $data['request_ip'] : null,
			'response_status'    => $data['response_status'],
			'response_size'      => !empty($data['response_size']) ? $data['response_size'] : null,
			'execution_time'     => !empty($data['execution_time']) ? $data['execution_time'] : null,
			'error_code'         => !empty($data['error_code']) ? $data['error_code'] : null,
			'param_type'         => !empty($data['param_type']) ? $data['param_type'] : null,
			'param_start'        => !empty($data['param_start']) ? $sanitizeDate($data['param_start']) : null,
			'param_end'          => !empty($data['param_end']) ? $sanitizeDate($data['param_end']) : null,
			'param_country'      => !empty($data['param_country']) ? '{' . implode(',', $data['param_country']) . '}' : null,
			'param_symbol'       => !empty($data['param_symbol']) ? '{' . implode(',', $data['param_symbol']) . '}' : null,
			'param_point'        => !empty($data['param_point']) ? '{' . implode(',', $data['param_point']) . '}' : null
		];
	}


	/**
	 * @inheritDoc
	 */
	public static function getInsertEventType(): string {
		return 'download_log_add';
	}


	/**
	 * @inheritDoc
	 */
	public static function getDeleteEventType(): string {
		return 'download_log_delete';
	}


	/**
	 * @inheritDoc
	 */
	public static function getUpdateEventType(): string {
		return 'download_log_update';
	}


	/**
	 * Do not log events for this model
	 *
	 * @return bool
	 */
	public static function isEventsEnabled(): bool {
		return false;
	}


}
