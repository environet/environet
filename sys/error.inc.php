<?php
/**
 * File error.inc.php
 *
 * @author Levente Peres - VIZITERV Environ Kft.
 *
 * Error and debug information handler
 * structure and procedures
 *
 * @package Environet
 */

use Environet\Sys\Config;

//! This stores the debug messages collected globally for later use.
global $debug_dumpster;

if (EN_DEV_MODE) {
	//! PHP Debug level - turn this off (0) for production use
	error_reporting(E_ALL);
	ini_set('display_errors', true);
} else {
	//! PHP Debug level - turn this off (0) for production use
	error_reporting(E_ERROR | E_WARNING | E_PARSE);
	ini_set('display_errors', false);
}


/**
 * General purpose debug message collector. Will also trace the source.
 * If the throwable argument is set, and the system is in dev-mode, it will throw it
 *
 * @param string $string Stores the actual debug messsage by the programmer
 */
function en_debug($string = "") {
	global $debug_dumpster;

	//! Store the temporary debug message line with trace
	$traceline = "";

	//! we only do this if debugging is actually enabled.
	if (Config::getInstance()->getErrorDebugEnable()) {
		$trace = debug_backtrace();
		$caller = $trace[1] ?? null;

		if ($caller) {
			$traceline .= date("Y-m-d H:i:s")." - Called by {$caller['function']}";
			if (isset($caller['class'])) {
				$traceline .= " in {$caller['class']}";
			}
		}
		$traceline .= " ---> ".$string;

		//! Writing out the debug log entry and appending it to the debug dumpster
		$debug_dumpster .= $traceline."\n";
		if (Config::getInstance()->getErrorFileDebugEnable()) {
			file_put_contents(Config::getInstance()->getErrorDebugPath(), $traceline.PHP_EOL, FILE_APPEND);
		}
	}
}


/**
 * Log an exception to file
 *
 * @param Throwable $exception
 */
function exception_logger(Throwable $exception) {
	file_put_contents(
		Config::getInstance()->getErrorExceptionPath(),
		sprintf("[%s]: %s\n%s\n\n", date('c'), $exception->getMessage(), $exception->getTraceAsString()),
		FILE_APPEND
	);
}


/**
 * This will draw a crude HTML section with the contents of the $debug_dumpster.
 */
function draw_debug_window() {
	global $debug_dumpster;
	if (Config::getInstance()->getErrorDebugEnable()) {
		echo "<div id='debug'><p><hr><p>".nl2br($debug_dumpster)."</p><hr></p></div>";
	}
}

/**
 * Global exception handler
 *
 * @param Throwable $exception
 *
 * @throws Throwable
 */
function exception_handler(Throwable $exception) {
	//Add debug message
	en_debug($exception->getMessage());

	//Add exception message
	exception_logger($exception);
	if (EN_DEV_MODE) {
		//If developer mode is enabled, display the expcetion after logging
		throw $exception;
	}
}
//Register the exception handler
set_exception_handler('exception_handler');


en_debug("ERROR HANDLING MODULE loaded.");
