<?php

//Operation modes
defined('EN_OP_MODE_DATA') || define('EN_OP_MODE_DATA', 1);
defined('EN_OP_MODE_DIST') || define('EN_OP_MODE_DIST', 2);
defined('EN_OP_MODE_CLIENT') || define('EN_OP_MODE_CLIENT', 3);

//Constants of mpoint types
defined('MPOINT_TYPE_HYDRO') || define('MPOINT_TYPE_HYDRO', 1);
defined('MPOINT_TYPE_METEO') || define('MPOINT_TYPE_METEO', 2);

//Constants of observed property types
defined('PROPERTY_TYPE_REALTIME') || define('PROPERTY_TYPE_REALTIME', 1);
defined('PROPERTY_TYPE_PROCESSED') || define('PROPERTY_TYPE_PROCESSED', 2);

defined('SYS_USERNAME') || define('SYS_USERNAME', '__SYS__');

defined('CONFIGURATION_PATH') || define('CONFIGURATION_PATH', SRC_PATH.'/conf/plugins/configurations');

//! Array static - Specify the comma separated names of the uploader classes / modules to be loaded and used
defined('EN_MOD_UPLOADERS') || define('EN_MOD_UPLOADERS', [
	\Environet\Plugins\Upload\Test\UploadTest::class
]);