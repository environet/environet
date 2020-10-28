<?php


/** @var Loader $autoloader */
define('SRC_PATH', realpath(__DIR__.'/../../..'));

define('TEST_RESOURCES_PATH', realpath(__DIR__.'/../resources'));
define('CONFIGURATION_PATH', realpath(TEST_RESOURCES_PATH.'/configurations'));

$autoloader = include __DIR__ . '/../../../sys/init_autoloader.inc.php';
$autoloader = include __DIR__ . '/../../../sys/constants.inc.php';
