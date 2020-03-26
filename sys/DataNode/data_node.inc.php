<?php
/**
 * File datnode.inc.php
 *
 * @author Levente Peres - VIZITERV Environ Kft.
 *
 * Data Node operation mode - high level functionalities
 *
 * A data node by default does not require much to go on, therefore we
 * do not load a lot of libraries. We load them only as/if needed.
 *
 * The data node can be responsible for a large number of data product updates by
 * itself. These data products must be configured in advance in the EN_MOD_UPLOADERS
 * section of the configuration file.
 *
 * Then the node calls their DoUpload functions whenever it is ran.
 *
 * This default behaviour can be changed by adding code to this section.
 *
 * @package Environet\Sys
 */

use Environet\Sys\DataNode\Interfaces\UploaderInterface;

require_once 'sys/userland/userland.inc.php';

//! We take the list of plugins we need to run and execute them in order.
$listplugins = EN_MOD_UPLOADERS;

/** @var UploaderInterface $plugin */
foreach ($listplugins as $pluginClass) {
	//Check if plugin class is invalid. It should exists and implement the uploader interface
	if (!(class_exists($pluginClass) && $plugin instanceof UploaderInterface)) {
		en_debug("Invalid uploader plugin: $pluginClass, ignore it.");
		continue;
	}

	//Create plugin instance and call the uploader method
	$plugin = new $pluginClass();
	$res = $plugin->doUpload();

	en_debug("Uploader ".$pluginClass." exited with result ", $res);
	unset($plugin);
}

en_debug("DATA NODE MODE MODULE LOADED");
