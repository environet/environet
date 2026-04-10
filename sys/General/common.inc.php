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

use Environet\Sys\Admin\Pages\BasePage;
use Environet\Sys\General\Exceptions\InvalidDateException;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Response;

defined('REGEX_NAME') || define('REGEX_NAME', '/^[\p{L}\s\-]*$/iu');
defined('REGEX_PHONE') || define('REGEX_PHONE', '/^[\d\-\+]*$/iu');
defined('REGEX_EMAIL') || define('REGEX_EMAIL', '/^((?!\.)[\w\-_.\+]*[^.])(@\w+)(\.\w+(\.\w+)?[^.\W])$/i');
defined('REGEX_ALPHANUMERIC') || define('REGEX_ALPHANUMERIC', '/[a-zA-Z0-9_-]/i');
defined('REGEX_USERNAME') || define('REGEX_USERNAME', REGEX_ALPHANUMERIC);
defined('REGEX_URL') || define('REGEX_URL', '/^(?:http(s)?:\/\/)?[\w.\-]+(?:\.[\w\.\-]+)+[\w\-\._~:\/?#[\]@!\$&\'\(\)\*\+,;=.]+$/i');
defined('REGEX_RIVERCODE') || define('REGEX_RIVERCODE', '/^[a-zA-Z0-9 _.-]*$/i');
defined('REGEX_RIVERBASINCODE') || define('REGEX_RIVERBASINCODE', '/^\d+$/i');


/**
 * Translate a string.
 *
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
 * Gete select options for property type
 * @return string[]
 */
function observedPropertyTypeOptions() {
	return [
		PROPERTY_TYPE_REALTIME  => 'Real time data',
		PROPERTY_TYPE_PROCESSED => 'Processed data'
	];
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
		if ($value !== '?' && !str_starts_with($value, ':')) {
			throw new QueryException('All values must be defined as PDO variables (:var of ?)');
		}
	}
}


/**
 * Convert a string to camel case from a snake case format, or other separated string
 *
 * @param string $string                 The string to convert
 * @param bool $capitalizeFirstCharacter If true, the first letter will be capital. The default is false.
 * @param string $separator              Separator string (default is _ for snake case)
 *
 * @return string
 */
function snakeToCamelCase($string, $capitalizeFirstCharacter = false, $separator = '_') {

	$str = str_replace(' ', '', ucwords(str_replace((string) $separator, ' ', (string) $string)));

	if (!$capitalizeFirstCharacter) {
		$str = lcfirst($str);
	}

	return $str;
}


/**
 * Convert a string from camel case to snake case.
 * The separator character is _ by default.
 *
 * @param string $separator
 *
 * @return string
 */
function camelCaseToSnake(string $string, string $separator = '_') {
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
	$response = new Response()->setStatusCode($code);
	if (EN_DEV_MODE) {
		$response->setContent($code);
	}

	return $response;
}


/**
 * Creates a http redirect response with the given url
 *
 * @param string $url The target url
 * @param int $code   HTTP status code
 *
 * @return Response
 */
function httpRedirect(string $url, $code = 302) {
	return new Response()->redirect($url, $code);
}


/**
 * Validate a data with some pre-defined rules, and regex patterns
 *
 * @param array $array         Array of fields
 * @param string $field        Field's name
 * @param string|null $pattern Regex pattern for validation
 * @param bool $required       If true, the data will be required, empty value not allowed
 *
 */
function validate(array $array, string $field, ?string $pattern = null, bool $required = false): bool {
	$isEmpty = empty($array[$field]);
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
 * @param string $field    The field's slug
 * @param array|null $data The optional data which can containe the field's vale
 *
 * @return mixed|null
 */
function formFieldValue(string $field, ?array $data = null, ?string $customPostField = null) {
	if (!empty($_POST)) {
		$postField = $customPostField ?? $field;

		//Has a post, use the value in the post array
		return $_POST[$postField] ?? null;
	} elseif (isset($data[$field])) {
		//No post data, but has an array with pre-populated values
		return $data[$field] ?: null;
	}

	//Empty
	return null;
}


function isFieldInvalidClass(string $field, $vars): string {
	return !empty($vars['fieldMessages'][$field][BasePage::MESSAGE_ERROR]) ? 'is-invalid' : '';
}


function getFieldInvalidMessage(string $field, $vars, ?string $default = null): ?string {
	return isset($vars['fieldMessages'][$field][BasePage::MESSAGE_ERROR]) ? implode("<br/>", $vars['fieldMessages'][$field][BasePage::MESSAGE_ERROR]) : $default;
}


/**
 * Call a specified function recursively each item in an array.
 *
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
 *
 * @return string
 */
function makeAccentInsensitiveRegex(string $string) {
	$stringArray = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);
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


/**
 * Generate a regex pattern based on date format
 *
 *
 * @throws Exception
 */
function dateFormatToRegex(string $dateFormat): string {
	$letters = str_split($dateFormat);
	$regexParts = array_map(function ($letter) {
		if (in_array($letter, ['d', 'm', 'y'], true)) {
			return '\d{2}';
		} elseif (in_array($letter, ['j', 'n'], true)) {
			return '\d{1,2}';
		} elseif ($letter === 'Y') {
			return '\d{4}';
		} elseif ($letter === 'N') {
			return '[1-7]';
		} elseif ($letter === 'w') {
			return '[0-6]';
		} elseif ($letter === 'z') {
			return '[0-9]{1,3}';
		} elseif ($letter === 'W') {
			return '\d{2}';
		} elseif ($letter === 'a') {
			return '[ap]m';
		} elseif ($letter === 'A') {
			return '[AP]M';
		} elseif ($letter === 'g') {
			return '[1-9][1-2]?';
		} elseif ($letter === 'G') {
			return '[0-9]{1,2}';
		} elseif ($letter === 'h') {
			return '[01][0-9]';
		} elseif ($letter === 'H') {
			return '[012][0-9]';
		} elseif (in_array($letter, ['i', 's'], true)) {
			return '[0-5][0-9]';
		} else {
			throw new Exception('This date format part for regex convert not supported: ' . $letter);
		}
	}, $letters);

	return implode('', $regexParts);
}


/**
 * Delete directory recursively
 *
 *
 * @codeCoverageIngore
 */
function rrmdir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (filetype($dir . "/" . $object) == "dir") {
					rrmdir($dir . "/" . $object);
				} else {
					unlink($dir . "/" . $object);
				}
			}
		}

		rmdir($dir);
	}
}


function isUploadDryRun(): bool {
	return defined('UPLOAD_DRY_RUN') && UPLOAD_DRY_RUN === true;
}


/**
 * @param DateTimeZone|string|null $timezone
 *
 * @throws Exception
 */
function createValidDate(string $dateString, $timezone = null): DateTime {
	$dateString = strtr($dateString, ['&nbsp;' => ' ']);
	try {
		$date = new DateTime($dateString, $timezone);
	} catch (Exception $e) {
		throw new InvalidDateException('Invalid date: ' . $dateString);
	}

	$lastErrors = DateTime::getLastErrors();
	$valid = $lastErrors === false || (DateTime::getLastErrors()['warning_count'] === 0 && DateTime::getLastErrors()['error_count'] === 0);
	if (!$valid) {
		throw new InvalidDateException('Invalid date: ' . $dateString);
	}

	return $date;
}


/**
 * Create an Atom formatted date time object from a string, with a fallback to a different format
 *
 *
 * @return DateTime|false
 */
function createAtomDateTime(string $timeString) {
	$time = DateTime::createFromFormat(DateTimeInterface::ATOM, $timeString);
	if ($time === false) {
		// Fallback to the format without timezone
		$time = DateTime::createFromFormat('Y-m-d\TH:i:s', $timeString, new DateTimeZone('UTC'));
	}

	return $time;
}


/**
 * Formats a valid date string to ISO 8601 date sting.
 */
function dateToISO(string $string): string {
	try {
		return new DateTime($string, new DateTimeZone('UTC'))->format('c');
	} catch (Exception $e) {
		return $string;
	}
}


/**
 * Generates slug from the string
 *
 * @param string $string Input string
 */
function slug(string $string): string {
	global $slugCharMap;
	$slug = mb_strtolower(strip_tags(trim($string))); //Convert to lower case

	//Special replace strings
	$spaceRpl = '-';
	$hyphenRpl = '-';

	//Replace spaces
	$slug = preg_replace('/[ \/.,]/', $spaceRpl, $slug);

	//Get array map
	$trArray = $slugCharMap ?? [];

	//Replace characters
	$slug = strtr($slug, $trArray);

	$trUnicodeArray = $slugArray['unicode'] ?? [];
	foreach ($trUnicodeArray as $replacement => $chars) {
		$chars = array_map(static function ($code) {
			return '\x{' . $code . '}';
		}, $chars);
		$slug = preg_replace('/[' . implode('', $chars) . ']/', $replacement, $slug);
	}

	$slug = preg_replace('/[^a-z0-9\-_]/', '', $slug); //Replace special characters
	$slug = preg_replace('/[\-]+/', $hyphenRpl, $slug);

	$slug = trim($slug, '-');

	return preg_replace('/-$/', '', $slug);
}


$slugCharMap = [
	// latin
	'À' => 'A',
	'Á' => 'A',
	'Â' => 'A',
	'Ã' => 'A',
	'Ä' => 'Ae',
	'Å' => 'A',
	'Æ' => 'AE',
	'Ç' => 'C',
	'È' => 'E',
	'É' => 'E',
	'Ê' => 'E',
	'Ë' => 'E',
	'Ì' => 'I',
	'Í' => 'I',
	'Î' => 'I',
	'Ï' => 'I',
	'Ð' => 'D',
	'Ñ' => 'N',
	'Ò' => 'O',
	'Ó' => 'O',
	'Ô' => 'O',
	'Õ' => 'O',
	'Ö' => 'O',
	'Ő' => 'O',
	'Ø' => 'O',
	'Ù' => 'U',
	'Ú' => 'U',
	'Û' => 'U',
	'Ü' => 'U',
	'Ű' => 'U',
	'Ý' => 'Y',
	'Þ' => 'TH',
	'ß' => 'ss',
	'à' => 'a',
	'á' => 'a',
	'â' => 'a',
	'ã' => 'a',
	'ä' => 'ae',
	'å' => 'a',
	'æ' => 'ae',
	'ç' => 'c',
	'è' => 'e',
	'é' => 'e',
	'ê' => 'e',
	'ë' => 'e',
	'ì' => 'i',
	'í' => 'i',
	'î' => 'i',
	'ï' => 'i',
	'ð' => 'd',
	'ñ' => 'n',
	'ò' => 'o',
	'ó' => 'o',
	'ô' => 'o',
	'õ' => 'o',
	'ö' => 'o',
	'ő' => 'o',
	'ø' => 'o',
	'ù' => 'u',
	'ú' => 'u',
	'û' => 'u',
	'ü' => 'u',
	'ű' => 'u',
	'ý' => 'y',
	'þ' => 'th',
	'ÿ' => 'y',
	'ẞ' => 'SS',

	// language specific
	'ا' => 'a',
	'أ' => 'a',
	'إ' => 'i',
	'آ' => 'aa',
	'ؤ' => 'u',
	'ئ' => 'e',
	'ء' => 'a',
	'ب' => 'b',
	'ت' => 't',
	'ث' => 'th',
	'ج' => 'j',
	'ح' => 'h',
	'خ' => 'kh',
	'د' => 'd',
	'ذ' => 'th',
	'ر' => 'r',
	'ز' => 'z',
	'س' => 's',
	'ش' => 'sh',
	'ص' => 's',
	'ض' => 'dh',
	'ط' => 't',
	'ظ' => 'z',
	'ع' => 'a',
	'غ' => 'gh',
	'ف' => 'f',
	'ق' => 'q',
	'ك' => 'k',
	'ل' => 'l',
	'م' => 'm',
	'ن' => 'n',
	'ه' => 'h',
	'و' => 'w',
	'ي' => 'y',
	'ى' => 'a',
	'ة' => 'h',
	'ﻻ' => 'la',
	'ﻷ' => 'laa',
	'ﻹ' => 'lai',
	'ﻵ' => 'laa',

	// Persian additional characters than Arabic
	'گ' => 'g',
	'چ' => 'ch',
	'پ' => 'p',
	'ژ' => 'zh',
	'ک' => 'k',
	'ی' => 'y',


	// Arabic diactrics
	'َ' => 'a',
	'ً' => 'an',
	'ِ' => 'e',
	'ٍ' => 'en',
	'ُ' => 'u',
	'ٌ' => 'on',
	'ْ' => '',

	// Arabic numbers
	'٠' => '0',
	'١' => '1',
	'٢' => '2',
	'٣' => '3',
	'٤' => '4',
	'٥' => '5',
	'٦' => '6',
	'٧' => '7',
	'٨' => '8',
	'٩' => '9',

	// Persian numbers
	'۰' => '0',
	'۱' => '1',
	'۲' => '2',
	'۳' => '3',
	'۴' => '4',
	'۵' => '5',
	'۶' => '6',
	'۷' => '7',
	'۸' => '8',
	'۹' => '9',

	// Burmese consonants
	'က'  => 'k',
	'ခ'  => 'kh',
	'ဂ'  => 'g',
	'ဃ'  => 'ga',
	'င'  => 'ng',
	'စ'  => 's',
	'ဆ'  => 'sa',
	'ဇ'  => 'z',
	'စျ' => 'za',
	'ည'  => 'ny',
	'ဋ'  => 't',
	'ဌ'  => 'ta',
	'ဍ'  => 'd',
	'ဎ'  => 'da',
	'ဏ'  => 'na',
	'တ'  => 't',
	'ထ'  => 'ta',
	'ဒ'  => 'd',
	'ဓ'  => 'da',
	'န'  => 'n',
	'ပ'  => 'p',
	'ဖ'  => 'pa',
	'ဗ'  => 'b',
	'ဘ'  => 'ba',
	'မ'  => 'm',
	'ယ'  => 'y',
	'ရ'  => 'ya',
	'လ'  => 'l',
	'ဝ'  => 'w',
	'သ'  => 'th',
	'ဟ'  => 'h',
	'ဠ'  => 'la',
	'အ'  => 'a',
	// consonant character combos
	'ြ'  => 'y',
	'ျ'  => 'ya',
	'ွ'  => 'w',
	'ြွ' => 'yw',
	'ျွ' => 'ywa',
	'ှ'  => 'h',
	// independent vowels
	'ဧ'    => 'e',
	'၏'    => '-e',
	'ဣ'    => 'i',
	'ဤ'    => '-i',
	'ဉ'    => 'u',
	'ဦ'    => '-u',
	'ဩ'    => 'aw',
	'သြော' => 'aw',
	'ဪ'    => 'aw',
	// numbers
	'၀' => '0',
	'၁' => '1',
	'၂' => '2',
	'၃' => '3',
	'၄' => '4',
	'၅' => '5',
	'၆' => '6',
	'၇' => '7',
	'၈' => '8',
	'၉' => '9',
	// virama and tone marks which are silent in transliteration
	'္' => '',
	'့' => '',
	'း' => '',

	// Czech
	'č' => 'c',
	'ď' => 'd',
	'ě' => 'e',
	'ň' => 'n',
	'ř' => 'r',
	'š' => 's',
	'ť' => 't',
	'ů' => 'u',
	'ž' => 'z',
	'Č' => 'C',
	'Ď' => 'D',
	'Ě' => 'E',
	'Ň' => 'N',
	'Ř' => 'R',
	'Š' => 'S',
	'Ť' => 'T',
	'Ů' => 'U',
	'Ž' => 'Z',

	// Dhivehi
	'ހ' => 'h',
	'ށ' => 'sh',
	'ނ' => 'n',
	'ރ' => 'r',
	'ބ' => 'b',
	'ޅ' => 'lh',
	'ކ' => 'k',
	'އ' => 'a',
	'ވ' => 'v',
	'މ' => 'm',
	'ފ' => 'f',
	'ދ' => 'dh',
	'ތ' => 'th',
	'ލ' => 'l',
	'ގ' => 'g',
	'ޏ' => 'gn',
	'ސ' => 's',
	'ޑ' => 'd',
	'ޒ' => 'z',
	'ޓ' => 't',
	'ޔ' => 'y',
	'ޕ' => 'p',
	'ޖ' => 'j',
	'ޗ' => 'ch',
	'ޘ' => 'tt',
	'ޙ' => 'hh',
	'ޚ' => 'kh',
	'ޛ' => 'th',
	'ޜ' => 'z',
	'ޝ' => 'sh',
	'ޞ' => 's',
	'ޟ' => 'd',
	'ޠ' => 't',
	'ޡ' => 'z',
	'ޢ' => 'a',
	'ޣ' => 'gh',
	'ޤ' => 'q',
	'ޥ' => 'w',
	'ަ' => 'a',
	'ާ' => 'aa',
	'ި' => 'i',
	'ީ' => 'ee',
	'ު' => 'u',
	'ޫ' => 'oo',
	'ެ' => 'e',
	'ޭ' => 'ey',
	'ޮ' => 'o',
	'ޯ' => 'oa',
	'ް' => '',

	// Greek
	'α' => 'a',
	'β' => 'v',
	'γ' => 'g',
	'δ' => 'd',
	'ε' => 'e',
	'ζ' => 'z',
	'η' => 'i',
	'θ' => 'th',
	'ι' => 'i',
	'κ' => 'k',
	'λ' => 'l',
	'μ' => 'm',
	'ν' => 'n',
	'ξ' => 'ks',
	'ο' => 'o',
	'π' => 'p',
	'ρ' => 'r',
	'σ' => 's',
	'τ' => 't',
	'υ' => 'y',
	'φ' => 'f',
	'χ' => 'x',
	'ψ' => 'ps',
	'ω' => 'o',
	'ά' => 'a',
	'έ' => 'e',
	'ί' => 'i',
	'ό' => 'o',
	'ύ' => 'y',
	'ή' => 'i',
	'ώ' => 'o',
	'ς' => 's',
	'ϊ' => 'i',
	'ΰ' => 'y',
	'ϋ' => 'y',
	'ΐ' => 'i',
	'Α' => 'A',
	'Β' => 'B',
	'Γ' => 'G',
	'Δ' => 'D',
	'Ε' => 'E',
	'Ζ' => 'Z',
	'Η' => 'I',
	'Θ' => 'TH',
	'Ι' => 'I',
	'Κ' => 'K',
	'Λ' => 'L',
	'Μ' => 'M',
	'Ν' => 'N',
	'Ξ' => 'KS',
	'Ο' => 'O',
	'Π' => 'P',
	'Ρ' => 'R',
	'Σ' => 'S',
	'Τ' => 'T',
	'Υ' => 'Y',
	'Φ' => 'F',
	'Χ' => 'X',
	'Ψ' => 'PS',
	'Ω' => 'W',
	'Ά' => 'A',
	'Έ' => 'E',
	'Ί' => 'I',
	'Ό' => 'O',
	'Ύ' => 'Y',
	'Ή' => 'I',
	'Ώ' => 'O',
	'Ϊ' => 'I',
	'Ϋ' => 'Y',

	// Latvian
	'ā' => 'a',
	// 'č' => 'c', // duplicate
	'ē' => 'e',
	'ģ' => 'g',
	'ī' => 'i',
	'ķ' => 'k',
	'ļ' => 'l',
	'ņ' => 'n',
	// 'š' => 's', // duplicate
	'ū' => 'u',
	// 'ž' => 'z', // duplicate
	'Ā' => 'A',
	// 'Č' => 'C', // duplicate
	'Ē' => 'E',
	'Ģ' => 'G',
	'Ī' => 'I',
	'Ķ' => 'k',
	'Ļ' => 'L',
	'Ņ' => 'N',
	// 'Š' => 'S', // duplicate
	'Ū' => 'U',
	// 'Ž' => 'Z', // duplicate

	// Macedonian
	'Ќ'  => 'Kj',
	'ќ'  => 'kj',
	'Љ'  => 'Lj',
	'љ'  => 'lj',
	'Њ'  => 'Nj',
	'њ'  => 'nj',
	'Тс' => 'Ts',
	'тс' => 'ts',

	// Polish
	'ą' => 'a',
	'ć' => 'c',
	'ę' => 'e',
	'ł' => 'l',
	'ń' => 'n',
	// 'ó' => 'o', // duplicate
	'ś' => 's',
	'ź' => 'z',
	'ż' => 'z',
	'Ą' => 'A',
	'Ć' => 'C',
	'Ę' => 'E',
	'Ł' => 'L',
	'Ń' => 'N',
	'Ś' => 'S',
	'Ź' => 'Z',
	'Ż' => 'Z',

	// Ukranian
	'Є' => 'Ye',
	'І' => 'I',
	'Ї' => 'Yi',
	'Ґ' => 'G',
	'є' => 'ye',
	'і' => 'i',
	'ї' => 'yi',
	'ґ' => 'g',

	// Romanian
	'ă' => 'a',
	'Ă' => 'A',
	'ș' => 's',
	'Ș' => 'S',
	// 'ş' => 's', // duplicate
	// 'Ş' => 'S', // duplicate
	'ț' => 't',
	'Ț' => 'T',
	'ţ' => 't',
	'Ţ' => 'T',

	// Russian https://en.wikipedia.org/wiki/Romanization_of_Russian
	// ICAO

	'а' => 'a',
	'б' => 'b',
	'в' => 'v',
	'г' => 'g',
	'д' => 'd',
	'е' => 'e',
	'ё' => 'yo',
	'ж' => 'zh',
	'з' => 'z',
	'и' => 'i',
	'й' => 'i',
	'к' => 'k',
	'л' => 'l',
	'м' => 'm',
	'н' => 'n',
	'о' => 'o',
	'п' => 'p',
	'р' => 'r',
	'с' => 's',
	'т' => 't',
	'у' => 'u',
	'ф' => 'f',
	'х' => 'kh',
	'ц' => 'ts',
	'ч' => 'ch',
	'ш' => 'sh',
	'щ' => 'shch',
	'ъ' => '',
	'ы' => 'y',
	'ь' => '',
	'э' => 'e',
	'ю' => 'yu',
	'я' => 'ya',
	'А' => 'A',
	'Б' => 'B',
	'В' => 'V',
	'Г' => 'G',
	'Д' => 'D',
	'Е' => 'E',
	'Ё' => 'Yo',
	'Ж' => 'Zh',
	'З' => 'Z',
	'И' => 'I',
	'Й' => 'I',
	'К' => 'K',
	'Л' => 'L',
	'М' => 'M',
	'Н' => 'N',
	'О' => 'O',
	'П' => 'P',
	'Р' => 'R',
	'С' => 'S',
	'Т' => 'T',
	'У' => 'U',
	'Ф' => 'F',
	'Х' => 'Kh',
	'Ц' => 'Ts',
	'Ч' => 'Ch',
	'Ш' => 'Sh',
	'Щ' => 'Shch',
	'Ъ' => '',
	'Ы' => 'Y',
	'Ь' => '',
	'Э' => 'E',
	'Ю' => 'Yu',
	'Я' => 'Ya',

	// Serbian
	'ђ' => 'dj',
	'ј' => 'j',
	// 'љ' => 'lj',  // duplicate
	// 'њ' => 'nj', // duplicate
	'ћ' => 'c',
	'џ' => 'dz',
	'Ђ' => 'Dj',
	'Ј' => 'j',
	// 'Љ' => 'Lj', // duplicate
	// 'Њ' => 'Nj', // duplicate
	'Ћ' => 'C',
	'Џ' => 'Dz',

	// Slovak
	'ľ' => 'l',
	'ĺ' => 'l',
	'ŕ' => 'r',
	'Ľ' => 'L',
	'Ĺ' => 'L',
	'Ŕ' => 'R',

	// Turkish
	'ş' => 's',
	'Ş' => 'S',
	'ı' => 'i',
	'İ' => 'I',
	// 'ç' => 'c', // duplicate
	// 'Ç' => 'C', // duplicate
	// 'ü' => 'u', // duplicate, see langCharMap
	// 'Ü' => 'U', // duplicate, see langCharMap
	// 'ö' => 'o', // duplicate, see langCharMap
	// 'Ö' => 'O', // duplicate, see langCharMap
	'ğ' => 'g',
	'Ğ' => 'G',

	// Vietnamese
	'ả' => 'a',
	'Ả' => 'A',
	'ẳ' => 'a',
	'Ẳ' => 'A',
	'ẩ' => 'a',
	'Ẩ' => 'A',
	'đ' => 'd',
	'Đ' => 'D',
	'ẹ' => 'e',
	'Ẹ' => 'E',
	'ẽ' => 'e',
	'Ẽ' => 'E',
	'ẻ' => 'e',
	'Ẻ' => 'E',
	'ế' => 'e',
	'Ế' => 'E',
	'ề' => 'e',
	'Ề' => 'E',
	'ệ' => 'e',
	'Ệ' => 'E',
	'ễ' => 'e',
	'Ễ' => 'E',
	'ể' => 'e',
	'Ể' => 'E',
	'ọ' => 'o',
	'Ọ' => 'o',
	'ố' => 'o',
	'Ố' => 'O',
	'ồ' => 'o',
	'Ồ' => 'O',
	'ổ' => 'o',
	'Ổ' => 'O',
	'ộ' => 'o',
	'Ộ' => 'O',
	'ỗ' => 'o',
	'Ỗ' => 'O',
	'ơ' => 'o',
	'Ơ' => 'O',
	'ớ' => 'o',
	'Ớ' => 'O',
	'ờ' => 'o',
	'Ờ' => 'O',
	'ợ' => 'o',
	'Ợ' => 'O',
	'ỡ' => 'o',
	'Ỡ' => 'O',
	'Ở' => 'o',
	'ở' => 'o',
	'ị' => 'i',
	'Ị' => 'I',
	'ĩ' => 'i',
	'Ĩ' => 'I',
	'ỉ' => 'i',
	'Ỉ' => 'i',
	'ủ' => 'u',
	'Ủ' => 'U',
	'ụ' => 'u',
	'Ụ' => 'U',
	'ũ' => 'u',
	'Ũ' => 'U',
	'ư' => 'u',
	'Ư' => 'U',
	'ứ' => 'u',
	'Ứ' => 'U',
	'ừ' => 'u',
	'Ừ' => 'U',
	'ự' => 'u',
	'Ự' => 'U',
	'ữ' => 'u',
	'Ữ' => 'U',
	'ử' => 'u',
	'Ử' => 'ư',
	'ỷ' => 'y',
	'Ỷ' => 'y',
	'ỳ' => 'y',
	'Ỳ' => 'Y',
	'ỵ' => 'y',
	'Ỵ' => 'Y',
	'ỹ' => 'y',
	'Ỹ' => 'Y',
	'ạ' => 'a',
	'Ạ' => 'A',
	'ấ' => 'a',
	'Ấ' => 'A',
	'ầ' => 'a',
	'Ầ' => 'A',
	'ậ' => 'a',
	'Ậ' => 'A',
	'ẫ' => 'a',
	'Ẫ' => 'A',
	// 'ă' => 'a', // duplicate
	// 'Ă' => 'A', // duplicate
	'ắ' => 'a',
	'Ắ' => 'A',
	'ằ' => 'a',
	'Ằ' => 'A',
	'ặ' => 'a',
	'Ặ' => 'A',
	'ẵ' => 'a',
	'Ẵ' => 'A',

	// symbols
	'“' => '"',
	'”' => '"',
	'‘' => '\'',
	'’' => '\'',
	'∂' => 'd',
	'ƒ' => 'f',
	'™' => '(TM)',
	'©' => '(C)',
	'œ' => 'oe',
	'Œ' => 'OE',
	'®' => '(R)',
	'†' => '+',
	'℠' => '(SM)',
	'…' => '...',
	'˚' => 'o',
	'º' => 'o',
	'ª' => 'a',
	'•' => '*',
	'၊' => ',',
	'။' => '.',

	// currency
	'$' => 'USD',
	'€' => 'EUR',
	'₢' => 'BRN',
	'₣' => 'FRF',
	'£' => 'GBP',
	'₤' => 'ITL',
	'₦' => 'NGN',
	'₧' => 'ESP',
	'₩' => 'KRW',
	'₪' => 'ILS',
	'₫' => 'VND',
	'₭' => 'LAK',
	'₮' => 'MNT',
	'₯' => 'GRD',
	'₱' => 'ARS',
	'₲' => 'PYG',
	'₳' => 'ARA',
	'₴' => 'UAH',
	'₵' => 'GHS',
	'¢' => 'cent',
	'¥' => 'CNY',
	'元' => 'CNY',
	'円' => 'YEN',
	'﷼' => 'IRR',
	'₠' => 'EWE',
	'฿' => 'THB',
	'₨' => 'INR',
	'₹' => 'INR',
	'₰' => 'PF',
];
