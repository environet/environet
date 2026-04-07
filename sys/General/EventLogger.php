<?php

namespace Environet\Sys\General;

use Environet\Sys\General\Db\Query\Insert;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class EventLogger
 *
 * Utility class for logging user interaction events.
 *
 * @package Environet\Sys\General
 * @author  SRG Group <dev@srg.hu>
 */
class EventLogger {


	public const EVENT_TYPE_LOGIN = 'login';
	public const EVENT_TYPE_LOGOUT = 'logout';
	public const EVENT_TYPE_LOGIN_ATTEMPT = 'login_attempt';

	public const EVENT_TYPE_USER_ADD = 'user_add';
	public const EVENT_TYPE_USER_UPDATE = 'user_update';
	public const EVENT_TYPE_USER_DELETE = 'user_delete';

	public const EVENT_TYPE_GROUP_ADD = 'group_add';
	public const EVENT_TYPE_GROUP_UPDATE = 'group_update';
	public const EVENT_TYPE_GROUP_DELETE = 'group_delete';

	public const EVENT_TYPE_OPERATOR_ADD = 'operator_add';
	public const EVENT_TYPE_OPERATOR_UPDATE = 'operator_update';

	public const EVENT_TYPE_HYDRO_MP_ADD = 'hydro_monitoring_point_add';
	public const EVENT_TYPE_HYDRO_MP_UPDATE = 'hydro_monitoring_point_update';
	public const EVENT_TYPE_HYDRO_MP_DELETE = 'hydro_monitoring_point_delete';

	public const EVENT_TYPE_HYDRO_OP_ADD = 'hydro_observed_property_add';
	public const EVENT_TYPE_HYDRO_OP_UPDATE = 'hydro_observed_property_update';
	public const EVENT_TYPE_HYDRO_OP_DELETE = 'hydro_observed_property_delete';

	public const EVENT_TYPE_HYDRO_SC_ADD = 'hydro_station_classification_add';
	public const EVENT_TYPE_HYDRO_SC_UPDATE = 'hydro_station_classification_update';
	public const EVENT_TYPE_HYDRO_SC_DELETE = 'hydro_station_classification_delete';

	public const EVENT_TYPE_HYDRO_RIVERBANK_ADD = 'hydro_riverbank_add';
	public const EVENT_TYPE_HYDRO_RIVERBANK_UPDATE = 'hydro_riverbank_update';
	public const EVENT_TYPE_HYDRO_RIVERBANK_DELETE = 'hydro_riverbank_delete';

	public const EVENT_TYPE_RIVER_ADD = 'river_add';
	public const EVENT_TYPE_RIVER_UPDATE = 'river_update';
	public const EVENT_TYPE_RIVER_DELETE = 'river_delete';

	public const EVENT_TYPE_RIVER_BASIN_ADD = 'river_basin_add';
	public const EVENT_TYPE_RIVER_BASIN_UPDATE = 'river_basin_update';
	public const EVENT_TYPE_RIVER_BASIN_DELETE = 'river_basin_delete';

	public const EVENT_TYPE_METEO_MP_ADD = 'meteo_monitoring_point_add';
	public const EVENT_TYPE_METEO_MP_UPDATE = 'meteo_monitoring_point_update';
	public const EVENT_TYPE_METEO_MP_DELETE = 'meteo_monitoring_point_delete';

	public const EVENT_TYPE_METEO_OP_ADD = 'meteo_observed_property_add';
	public const EVENT_TYPE_METEO_OP_UPDATE = 'meteo_observed_property_update';
	public const EVENT_TYPE_METEO_OP_DELETE = 'meteo_observed_property_delete';

	public const EVENT_TYPE_METEO_SC_ADD = 'meteo_station_classification_add';
	public const EVENT_TYPE_METEO_SC_UPDATE = 'meteo_station_classification_update';
	public const EVENT_TYPE_METEO_SC_DELETE = 'meteo_station_classification_delete';

	public const EVENT_TYPE_UPLOAD_DATA = 'upload_data';
	public const EVENT_TYPE_UPLOAD_DATA_ERROR = 'upload_data_error';


	/**
	 * Insert the event and its data to event_logs table.
	 *
	 * @param string       $evenType
	 * @param array|string $data
	 * @param int|null $operatorId
	 * @param string|null $date
	 *
	 * @throws QueryException
	 * @uses Insert::run
	 */
	public static function log(string $evenType, $data, ?int $operatorId = null, ?string $date = null) {
		$data = is_array($data) ? json_encode($data) : $data;
		$userId = $_SESSION[Request::AUTH_SESSION_KEY] ?? null;

		if (is_null($date)) {
			$date = date('Y-m-d H:i:s');
		}
		new Insert()
			->table('event_logs')
			->addSingleData([
				'event_type'  => $evenType,
				'data'        => $data,
				'user_id'     => $userId,
				'operator_id' => $operatorId,
				'created_at'  => $date,
			])
			->run();
	}


}