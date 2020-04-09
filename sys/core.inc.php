<?php

/**
 * File core.inc.php
 *
 * @author Levente Peres - VIZITERV Environ Kft.
 *
 * Core task node and bootstrap for Environet
 *
 * @package Environet
 */

use Environet\Sys\Config;

$request = new Environet\Sys\General\Request();

//! Check the configured operation mode and switch behaviour accordingly
$opMode = Config::getInstance()->getOpMode();
switch ($opMode) {
	case EN_OP_MODE_DIST:
		require_once SRC_PATH.'/sys/DistributionNode/distribution_node.inc.php';
		break;
	case EN_OP_MODE_DATA:
		require_once SRC_PATH.'/sys/DataNode/data_node.inc.php';
		break;
	case EN_OP_MODE_CLIENT:
		//Not implemeneted
		break;
}

en_debug("CORE TASK NODE LOADED");
