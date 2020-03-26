<?php
/**
 * File console.inc.php
 *
 * @author Levente Peres - VIZITERV Environ Kft.
 *
 * Class for enabling a developer to directly interact with the
 * inner structure of the system, bypassing lanes, defining new ones.
 *
 * WARNING - HEAVY SECURITY RISK
 * THIS MODULE MUST BE DISABLED FOR PRODUCTION USE - DO ***NOT*** ENABLE IN PRODUCTION ENVIRONMENT
 *
 * @package Environet\Sys
 */

if (EN_DEBUG_CONSOLE) { //!< Only activate console processes if console is enabled
	en_debug("Console engaged.");
	if (!empty($argv) && $argv[1] == "uploadtest") { //!< case we need to test an upload
		en_debug("Requested uploadtest from console.");
		define('EN_CONSOLE_CORE_OVERRIDE', true);
		define("EN_CONSOLE_FORCE_DATANODE", true); //!< We force the core into datanode mode
		en_debug("Fake environment setup complete.");
	}
}
