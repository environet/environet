<?php
/*!
 * \file dnode.inc.php
 *
 * \author Levente Peres - VIZITERV Environ Kft.
 * \date
 *
 * Distribution node operation mode - high level functionalities
 *
 * @package Environet
 */

/* Run the init procedure
 * - Check and connect to database
 * - Check if we are in Install mode, perform install if required. Install mode also checks and preps missing tables for new or
 * modified plugins / modules / filters.
 * - Load modules
 * - Check if this is a CRON or other internal operation
 * - Check if this is a cross-node networking operation
 * - Check if this is a userland operation
 * - Close database connection
 */

use Environet\Sys\Admin\AdminHandler;
use Environet\Sys\Download\DownloadHandler;
use Environet\Sys\Api\JsonApiHandler;
use Environet\Sys\Upload\UploadHandler;

switch (true) {
	case $request->isAdmin():
		require_once SRC_PATH.'/sys/viewhelpers.inc.php';
		//If request is a valid admin request, call the enty point of the administration area
		echo (new AdminHandler($request))->handleRequest();
		break;
	case $request->isUpload():
		echo (new UploadHandler($request))->handleRequest();
		break;
	case $request->isDownload():
		echo (new DownloadHandler($request))->handleRequest();
		break;
	case $request->isJsonApi():
		echo (new JsonApiHandler($request))->handleRequest();
		break;
}

en_debug("DISTRIBUTION NODE MODE MODULE LOADED");
