<?php
/**
 * File index.php
 *
 * @uthor Levente Peres - VIZITERV Environ Kft.
 *
 * Root connection broker..
 *
 * This is the single point of contact for the WebAPI and all other interfaces. One of the few files to be called directly.
 *
 * @package Environet
 */

use Environet\Sys\Config;

//The root path of the source code. Every include and other file links should be relative to this.
define('SRC_PATH', realpath(__DIR__.'/..'));

require_once SRC_PATH.'/sys/init_autoloader.inc.php';

require_once SRC_PATH.'/sys/constants.inc.php';

$config = new Config();

date_default_timezone_set($config->getTimezone());

//! Load error handling library
require_once SRC_PATH.'/sys/error.inc.php';

//! Init sesson
require_once SRC_PATH.'/sys/session.inc.php';

//! Load bootstrap module
require_once SRC_PATH.'/sys/core.inc.php';

en_debug("EnviroNET 0.1 CONCLUDED");
