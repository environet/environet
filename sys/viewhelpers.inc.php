<?php

use Environet\Sys\General\Request;

/**
 * Render an array of paginator numbers. Not all numbers will be added from 1 to max page.
 * Some items will be skipped, and replaced with '...'
 *
 * @param int $currentPage Number of current page
 * @param int $maxPage     Page count
 *
 * @return array Array of numbers, and '...' items
 */
function getPaginatorParts(int $currentPage, int $maxPage) {
	$parts = [];
	$skipState = false;
	for ($i = 1; $i <= $maxPage; $i ++) {
		//Skip numbers is not 1, not the last, and not near current page
		$skip = ($i > 1 && $i < $currentPage - 1 && $currentPage > 3) ||
				($i < $maxPage && $i > $currentPage + 1 && $currentPage < $maxPage - 3);
		if ($skip && $skipState === false) {
			//Add '...' part only once, set a flag to true for next skipped pages
			$parts[] = '...';
			$skipState = true;
		} elseif (!$skip) {
			//Add number part
			$parts[] = $i;
			$skipState = false;
		}
	}

	return $parts;
}

/**
 * Render a sortable column with valid links, and a span with a class to display current sort state
 *
 * @param string $label Label of the column header
 * @param string $name  Name of field. This will be sent as order_by parameters
 *
 * @return string
 */
function sortableColumn(string $label, string $name) {

	$currentBy = getCurrentOrderBy();
	$currentDir = getCurrentOrderDir();

	//Column is active, if the currentBy is the column's name
	$active = ($currentDir && $currentBy === $name);

	//Add order params to href, and reset page param to jump to first page
	$href = hrefWithParams([
		'order_by'  => $name,
		'order_dir' => ($active && $currentDir === 'ASC') ? 'DESC' : 'ASC',
		'page'      => null
	]);

	//Set the class for marker (empty if order is not active for this column)
	$markerClass = 'fas fa-sort';

	if($active) {
	    $markerClass = $currentDir === 'ASC' ?  'fas fa-sort-down' : 'fas fa-sort-up';
    }

	//Render HTML link
	return sprintf('<a href="%s">%s</a><span class="sort-icon %s"></span>', $href, $label, $markerClass);

}

/**
 * @return string|null
 */
function getCurrentOrderBy(): ?string {
	global $request;
	return $request->getQueryParam('order_by');
}

/**
 * @return string|null
 */
function getCurrentOrderDir(): ?string {
	global $request;
	return $request->getQueryParam('order_dir');
}

/**
 * Build a href with adding (and maybe removing) extra parameters, with keeping the existing GET params
 *
 * @param array $params Array of params to add. If a parameter value is null, it will be removed from the url
 *
 * @return string
 */
function hrefWithParams(array $params) {
	/** @var Request $request */
	global $request;

	$originalParams = $request->getQueryParams();

	//Add all extra params
	foreach ($params as $key => $value) {
		$originalParams[$key] = $value;
	}

	//Filter out null parameters
	$originalParams = array_filter($originalParams, function ($param) {
		return !is_null($param);
	});

	//Build url
	return $request->getPath() . '?' . http_build_query($originalParams);
}

/**
 * Parse and format a date (string or object)
 *
 * @param mixed  $date   DateTime object or string
 * @param string $format The output format of datetime
 *
 * @return string|null
 */
function dateFormat($date, $format = 'Y. m. d. H:i:s'): ?string {
	if ($date instanceof DateTimeInterface) {
		//It's already a datetime object, format it
		return $date->format($format);
	}

	try {
		//Parse date, and format it
		$date = new DateTime($date);

		return $date->format($format);
	} catch (Exception $e) {
		//Can't parse date
		return null;
	}
}

/**
 * Convert connection type to a human-readable string
 *
 * @param string $type
 *
 * @return mixed|null
 */
function userGroupConnectionType(string $type) {
	$types = [
		'direct' => 'Direct',
		'group'  => 'By group',
		'both'   => 'Direct & by group',
	];

	return $types[$type] ?? null;
}

/**
 * Create select options based on an array, and the selected value
 *
 * @param array        $options  Array of options (key => value)
 * @param string|array $selected Selected value(s)
 * @param string|null  $emptyOption Label for empty option
 *
 * @return string
 */
function selectOptions(array $options, $selected = null, string $emptyOption = null) {
	//Selected must be an array
	if (is_null($selected)) {
		$selected = [];
	} else {
		$selected = (array) $selected;
	}

    // Sort options by value
	asort($options);

	$optionsHtml = [];
	//Create empty option
	if (!is_null($emptyOption)) {
		$optionsHtml[] = sprintf('<option value="">%s</option>', $emptyOption);
	}
	//Create all options with selected flags
	foreach ($options as $value => $label) {
		$isSelected = in_array($value, $selected);
		$optionsHtml[] = sprintf('<option value="%s" %s>%s</option>', $value, $isSelected ? 'selected' : '', $label);
	}

	return implode("\n", $optionsHtml);
}


/**
 * Renders a template part with the provided context variables
 *
 * @param string $templatePath
 * @param array  $vars
 * @return string
 */
function includeTemplatePart($templatePath, $vars = []) {
	ob_start();
	extract($vars);
	include 'Admin/templates/parts/' . $templatePath;
	$contents = ob_get_contents();
	ob_end_clean();
	return $contents;
}


/**
 * Check that a path matches the current request path, with a given
 *
 * @param string $path
 * @param int $matchExtra
 * @return bool
 */
function isRoute($path, $matchExtra): bool {
	global $request;
	$requestPath = explode('/', trim($request->getPath(), '/'));
	$path = explode('/', trim($path, '/'));
	if (count($requestPath) > count($path) + $matchExtra) {
		return false;
	}
	foreach ($path as $i => $part) {
		if (!array_key_exists($i, $requestPath)) {
			return false;
		}
		if ($requestPath[$i] !== $part) {
			return false;
		}
	}
	return true;
}
