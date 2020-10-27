<?php


/** @var Loader $autoloader */
define('SRC_PATH', realpath(__DIR__.'/../../..'));

$autoloader = include __DIR__ . '/../../../sys/init_autoloader.inc.php';
$autoloader = include __DIR__ . '/../../../sys/constants.inc.php';
