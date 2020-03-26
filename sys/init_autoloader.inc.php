<?php


//Load file of autoloader class
require_once SRC_PATH.'/sys/autoloader.php';

//Get or create the instance of the autoloader
$autoloader = Loader::getInstance();

//Init autoloader with core namespace prefixes
$autoloader->addPrefix('Environet\Sys', SRC_PATH.'/sys');
$autoloader->addPrefix('Environet\Confg', SRC_PATH.'/config');
$autoloader->addPrefix('Environet\Tools', SRC_PATH.'/tools');
$autoloader->addPrefix('Environet\Testing', SRC_PATH.'/testing');

//Load seom includes with the autoloader
$autoloader->loadIncFiles([
	SRC_PATH.'/sys/General/common.inc.php' //Load general-purpose libraries
]);

spl_autoload_register([$autoloader, 'loadClass']);
