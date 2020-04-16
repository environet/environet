<?php
/**
 * File common.inc.php
 *
 * @author  Levente Peres - VIZITERV Environ Kft.
 *
 * General use library - common reusable functions.
 *
 * This file and it's siblings contain a number of common, simple functions that can be
 * used to solve programming-related problems, like concatenating strings, etc.
 *
 * @package Environet\Sys\General
 */

use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Response;


defined('REGEX_NAME') || define('REGEX_NAME', '/^[\p{L}\s\-]*$/iu');
defined('REGEX_PHONE') || define('REGEX_PHONE', '/^[\d\-\+]*$/iu');
defined('REGEX_EMAIL') || define('REGEX_EMAIL', '/^((?!\.)[\w\-_.]*[^.])(@\w+)(\.\w+(\.\w+)?[^.\W])$/i');
defined('REGEX_ALPHANUMERIC') || define('REGEX_ALPHANUMERIC', '/[a-zA-Z0-9_-]/i');
defined('REGEX_USERNAME') || define('REGEX_USERNAME', REGEX_ALPHANUMERIC);
defined('REGEX_URL') || define('REGEX_URL', '/^(?:http(s)?:\/\/)?[\w.\-]+(?:\.[\w\.\-]+)+[\w\-\._~:\/?#[\]@!\$&\'\(\)\*\+,;=.]+$/i');
defined('REGEX_RIVERCODE') || define('REGEX_RIVERCODE', '/^[a-zA-Z_-]*$/i');


/**
 * Translate a string.
 *
 * @param $str
 *
 * @return mixed
 */
function __($str) {
	return $str;
}

/**
 * Non-cryptographically secure random string generator
 *
 * Quick-fix for rapidly generating arbitrary temporary ID-s.
 *
 * DO NOT USE FOR SECURE TRANSACTIONS!
 *
 * @param int $length The length of the string
 *
 * @return string Random String
 *
 */
function NCSRandStr($length = 20) {
	return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
}


/**
 * Check if all values are defined as variable. The system support queries only with PDO-parameters
 *
 * @param string|array $values
 *
 * @return void
 * @throws QueryException
 */
function checkDbInputValues($values) {
	if (!is_array($values)) {
		$values = [$values];
	}
	foreach ($values as $value) {
		if ($value !== '?' && substr($value, 0, 1) !== ':') {
			throw new QueryException('All values must be defined as PDO variables (:var of ?)');
		}
	}
}


/**
 * Convert a string to camel case from a snake case format, or other separated string
 *
 * @param string $string                   The string to convert
 * @param bool   $capitalizeFirstCharacter If true, the first letter will be capital. The default is false.
 * @param string $separator                Separator string (default is _ for snake case)
 *
 * @return string
 */
function snakeToCamelCase($string, $capitalizeFirstCharacter = false, $separator = '_') {

	$str = str_replace(' ', '', ucwords(str_replace($separator, ' ', $string)));

	if (!$capitalizeFirstCharacter) {
		$str = lcfirst($str);
	}

	return $str;
}

/**
 * Convert a string from camel case to snake case.
 * The separator character is _ by default.
 *
 * @param string $string
 * @param string $separator
 *
 * @return string
 */
function camelCaseToSnake(string $string, $separator = '_') {
	preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $string, $matches);
	$ret = $matches[0];
	foreach ($ret as &$match) {
		$match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
	}

	return implode($separator, $ret);
}

/**
 * Creates an empty http response with the given status code
 *
 * @param int $code HTTP status code
 *
 * @return Response
 */
function httpErrorPage($code = 500) {
	$response = (new Response())->setStatusCode($code);
	if (EN_DEV_MODE) {
		$response->setContent($code);
	}

	return $response;
}

/**
 * Creates a http redirect response with the given url
 *
 * @param string $url  The target url
 * @param int    $code HTTP status code
 *
 * @return Response
 */
function httpRedirect(string $url, $code = 302) {
	return (new Response())->redirect($url, $code);
}

/**
 * Validate a data with some pre-defined rules, and regex patterns
 *
 * @param array  $array    Array of fields
 * @param string $field    Field's name
 * @param string $pattern  Regex pattern for validation
 * @param bool   $required If true, the data will be required, empty value not allowed
 *
 * @return bool
 */
function validate(array $array, string $field, string $pattern = null, bool $required = false): bool {
	$isEmpty = !isset($array[$field]) || empty($array[$field]);
	if ($required && $isEmpty) {
		//Empty value not allowed
		return false;
	} elseif ($isEmpty) {
		//Empty allowed, do not continue to pattern-check
		return true;
	}

	if ($pattern) {
		//Check with regex patters
		return preg_match($pattern, $array[$field]) > 0;
	}

	return true;
}

/**
 * Get a form field's value from post, or from pre-populated data
 *
 * @param string     $field The field's slug
 * @param array|null $data  The optional data which can containe the field's vale
 *
 * @return mixed|null
 */
function formFieldValue(string $field, array $data = null) {
	if (!empty($_POST)) {
		//Has a post, use the value in the post array
		return $_POST[$field] ?? null;
	} elseif (isset($data[$field])) {
		//No post data, but has an array with pre-populated values
		return $data[$field] ?? null;
	}

	//Empty
	return null;
}

/**
 * Call a specified function recursively each item in an array.
 *
 * @param array    $data
 * @param callable $function
 *
 * @return array
 */
function arrayMapRecursive(array $data, callable $function) {
	foreach ($data as $key => $value) {
		if (is_array($value)) {
			$data[$key] = arrayMapRecursive($value, $function);
		} else {
			$data[$key] = $function($value);
		}
	}

	return $data;
}

/**
 * Make strings accent insensitive on db search.
 *
 * @param string $string
 *
 * @return string
 */
function makeAccentInsensitiveRegex(string $string) {
	$stringArray = preg_split('//u', $string, null, PREG_SPLIT_NO_EMPTY);
	$map = [
		['a', 'à', 'å', 'á', 'â', 'ä', 'ã', 'å', 'ą'],
		['e', 'è', 'é', 'ê', 'ë', 'ę'],
		['i', 'ì', 'í', 'î', 'ï', 'ı'],
		['o', 'ò', 'ó', 'ô', 'õ', 'ő', 'ö', 'ø'],
		['u', 'ù', 'ú', 'û', 'ü', 'ű'],
		['c', 'ç', 'ć', 'č'],
		['z', 'ż', 'ź', 'ž'],
		['s', 'ś', 'ş', 'š'],
		['n', 'ñ', 'ń'],
		['y', 'ý', 'Ÿ'],
		['l', 'ł'],
		['d', 'đ'],
		['g', 'g'],
		['h', 'ĥ'],
		['j', 'ĵ']
	];

	foreach ($stringArray as &$char) {
		foreach ($map as $row) {
			if (in_array($char, $row) !== false) {
				$char = '[' . implode('', $row) . ']';
			}
		}
	}

	return implode('', $stringArray);
}

/**
 * Build and ini file from a 2-level array
 *
 * @param array $array
 *
 * @return string
 */
function buildIni(array $array) {
	$lines = [];
	foreach ($array as $groupName => $groupProperties) {
		// Add group
		$lines[] = "[$groupName]";
		if (!is_array($groupProperties)) {
			continue;
		}
		foreach ($groupProperties as $propertyName => $propertyValue) {
			// Add property
			if (is_bool($propertyValue)) {
				$propertyValue = $propertyValue ? 'true' : 'false';
			}
			$lines[] = "$propertyName=$propertyValue";
		}
		$lines[] = '';
	}

	return implode("\n", $lines);
}