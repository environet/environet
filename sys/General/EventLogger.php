<?php

namespace Environet\Sys\General;

use Environet\Sys\Admin\AdminHandler;
use Environet\Sys\General\Db\Query\Insert;

/**
 * Class EventLogger
 *
 * Utility class for logging user interaction events.
 *
 * @package Environet\Sys\General
 * @author  SRG Group <dev@srg.hu>
 */
class EventLogger {

	const EVENT_TYPE_LOGIN         = 'login';
	const EVENT_TYPE_LOGOUT        = 'logout';
	const EVENT_TYPE_LOGIN_ATTEMPT = 'login_attempt';

	const EVENT_TYPE_USER_ADD    = 'user_add';
	const EVENT_TYPE_USER_UPDATE = 'user_update';
	const EVENT_TYPE_USER_DELETE = 'user_delete';

	const EVENT_TYPE_GROUP_ADD    = 'group_add';
	const EVENT_TYPE_GROUP_UPDATE = 'group_update';
	const EVENT_TYPE_GROUP_DELETE = 'group_delete';

	const EVENT_TYPE_OPERATOR_ADD    = 'operator_add';
	const EVENT_TYPE_OPERATOR_UPDATE = 'operator_update';

	const EVENT_TYPE_HYDRO_MP_ADD    = 'hydro_monitoring_point_add';
	const EVENT_TYPE_HYDRO_MP_UPDATE = 'hydro_monitoring_point_update';
	const EVENT_TYPE_HYDRO_MP_DELETE = 'hydro_monitoring_point_delete';

	const EVENT_TYPE_HYDRO_OP_ADD    = 'hydro_observed_property_add';
	const EVENT_TYPE_HYDRO_OP_UPDATE = 'hydro_observed_property_update';
	const EVENT_TYPE_HYDRO_OP_DELETE = 'hydro_observed_property_delete';

	const EVENT_TYPE_HYDRO_SC_ADD    = 'hydro_station_classification_add';
	const EVENT_TYPE_HYDRO_SC_UPDATE = 'hydro_station_classification_update';
	const EVENT_TYPE_HYDRO_SC_DELETE = 'hydro_station_classification_delete';
	
	const EVENT_TYPE_HYDRO_RIVERBANK_ADD    = 'hydro_riverbank_add';
	const EVENT_TYPE_HYDRO_RIVERBANK_UPDATE = 'hydro_riverbank_update';
	const EVENT_TYPE_HYDRO_RIVERBANK_DELETE = 'hydro_riverbank_delete';

	const EVENT_TYPE_WATERBODY_ADD    = 'waterbody_add';
	const EVENT_TYPE_WATERBODY_UPDATE = 'waterbody_update';
	const EVENT_TYPE_WATERBODY_DELETE = 'waterbody_delete';

	const EVENT_TYPE_METEO_MP_ADD    = 'meteo_monitoring_point_add';
	const EVENT_TYPE_METEO_MP_UPDATE = 'meteo_monitoring_point_update';
	const EVENT_TYPE_METEO_MP_DELETE = 'meteo_monitoring_point_delete';

	const EVENT_TYPE_METEO_OP_ADD    = 'meteo_observed_property_add';
	const EVENT_TYPE_METEO_OP_UPDATE = 'meteo_observed_property_update';
	const EVENT_TYPE_METEO_OP_DELETE = 'meteo_observed_property_delete';

	const EVENT_TYPE_METEO_SC_ADD    = 'meteo_station_classification_add';
	const EVENT_TYPE_METEO_SC_UPDATE = 'meteo_station_classification_update';
	const EVENT_TYPE_METEO_SC_DELETE = 'meteo_station_classification_delete';


	/**
	 * Insert the event and its data to event_logs table.
	 *
	 * @param string       $evenType
	 * @param array|string $data
	 * @param int|null     $operatorId
	 *
	 * @throws Exceptions\QueryException
	 * @uses \Environet\Sys\General\Db\Query\Insert::run()
	 */
	public static function log(string $evenType, $data, int $operatorId = null) {
		$data = is_array($data) ? json_encode($data) : $data;
		$userId = isset($_SESSION[Request::AUTH_SESSION_KEY]) ? $_SESSION[Request::AUTH_SESSION_KEY] : null;

		(new Insert())
			->table('event_logs')
			->addSingleData([
				'event_type'  => $evenType,
				'data'        => $data,
				'user_id'     => $userId,
				'operator_id' => $operatorId,
				'created_at'  => date('Y-m-d H:i:s'),
			])
			->run();
	}


}