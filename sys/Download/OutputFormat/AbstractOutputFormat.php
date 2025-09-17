<?php

namespace Environet\Sys\Download\OutputFormat;

use Environet\Sys\Config;
use Environet\Sys\Download\Exceptions\DownloadException;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Request;
use Environet\Sys\General\Response;
use Exception;

/**
 * Class AbstractOutputFormat
 */
abstract class AbstractOutputFormat {

	protected array $options = [];

	protected Config $globalConfig;


	abstract public function outputResults(array $results, array $queryMeta): Response;


	public function __construct() {
		$this->globalConfig = Config::getInstance();
	}


	/**
	 * Should throw a DownloadException if the request is invalid
	 *
	 * @param Request $request
	 * @param array   $formatOptions
	 *
	 * @return void
	 * @throws DownloadException
	 */
	public function validateRequest(Request $request, array $formatOptions): void {
		if (!empty($formatOptions)) {
			//Find the invalid keys in the request's format_options array
			$invalidKeys = array_diff(array_keys($formatOptions), array_keys($this->options));
			if (!empty($invalidKeys)) {
				throw new DownloadException(307);
			}
		}
	}


	/**
	 * Set options with type checking of default values, and casting
	 *
	 * @param array $options
	 *
	 * @return AbstractOutputFormat
	 * @throws Exception
	 */
	public function setOptions(array $options): AbstractOutputFormat {
		foreach ($options as $key => $value) {
			$type = gettype($this->options[$key]);
			switch ($type) {
				case 'boolean':
					$this->options[$key] = (bool) $value;
					break;
				case 'integer':
					$this->options[$key] = (int) $value;
					break;
				case 'double':
					$this->options[$key] = (float) $value;
					break;
				case 'string':
					$this->options[$key] = (string) $value;
					break;
				default:
					//Other options formats are not supported
					throw new Exception('Format option type is not supported');
			}
		}

		return $this;
	}


	/**
	 * Add headers to the response
	 *
	 * @param Response $response
	 * @param array    $headers
	 *
	 * @return $this
	 */
	protected function addResponseHeaders(Response $response, array $headers): AbstractOutputFormat {
		foreach ($headers as $key => $value) {
			$response->addHeader("$key: $value");
		}

		return $this;
	}


	/**
	 * Fetch station data from the database based on restuls
	 *
	 * @param array      $results   Results from the query
	 * @param array      $queryMeta Metadata about the query
	 * @param array|null $columns   Columns to select from the database
	 *
	 * @return array
	 * @throws QueryException
	 */
	protected function getStationData(array $results, array $queryMeta, array $columns = null): array {
		$ids = array_values(array_unique(array_column($results, 'mpoint_id'))); //Find unique mpoint ids
		if (empty($ids)) {
			return [];
		}
		$tableName = $queryMeta['type'] . 'point';

		//Build columns, add a _keyid column to the end, it will be used to map the results
		$columns ??= [$tableName . '.*'];
		$columns[] = 'point.id as _keyid';

		//Build and run the query
		$query = (new Select())->from("$tableName as point");
		$query->join('operator', 'point.operatorid = operator.id');
		if ($queryMeta['type'] === 'hydro') {
			$query->join('river', 'point.eucd_riv = river.eucd_riv', Query::JOIN_LEFT);
		}
		$query->join('river_basin', 'point.river_basin_id = river_basin.id', Query::JOIN_LEFT);

		$results = $query->select($columns)->whereIn('point.id', $ids, 'mpointId')->where('point.is_active = true')->orderBy('1')->run();

		//Map the results to use the _keyid as the key, and remove the _keyid from the values
		return array_combine(
			array_map(fn($item) => $item['_keyid'], $results),
			array_map(fn($item) => array_diff_key($item, ['_keyid' => null]), $results),
		);
	}


	/**
	 * Fetch property data from the database based on results
	 *
	 * @param array      $results   Results from the query
	 * @param array      $queryMeta Metadata about the query
	 * @param array|null $columns   Columns to select from the database
	 *
	 * @return array
	 * @throws QueryException
	 */
	protected function getPropertyData(array $results, array $queryMeta, array $columns = null): array {
		$ids = array_values(array_unique(array_column($results, 'property_id'))); //Find unique property ids
		if (empty($ids)) {
			return [];
		}
		$tableName = $queryMeta['type'] . '_observed_property';

		//Build columns, add a _keyid column to the end, it will be used to map the results
		$columns ??= [$tableName . '.*'];
		$columns[] = 'observed_property.id as _keyid';

		//Build and run the query
		$query = (new Select())->from("$tableName as observed_property")->select($columns);
		$results = $query->whereIn('observed_property.id', $ids, 'propertyId')->orderBy('UPPER(observed_property.symbol)')->run();

		//Map the results to use the _keyid as the key, and remove the _keyid from the values
		return array_combine(
			array_map(fn($item) => $item['_keyid'], $results),
			array_map(fn($item) => array_diff_key($item, ['_keyid' => null]), $results),
		);
	}


	protected function generateFilename(array $propertySymbols, array $queryMeta): string {
		// Generate export filename
		$filenameParts = [];
		if (is_string($this->globalConfig->getExportTitle()) && !empty($this->globalConfig->getExportTitle())) {
			$filenameParts[] = str_replace(' ', '_', preg_replace('/\W+/iu', '_', $this->globalConfig->getExportTitle())); //Use export title if given
		} //Base name

		if ($propertySymbols) {
			$filenameParts[] = implode('-', array_unique([reset($propertySymbols), end($propertySymbols)])); //First and last property symbol
		}
		if (!empty(($countries = $queryMeta['params']['countries']))) {
			sort($countries);
			$filenameParts[] = implode('-', array_unique($countries)); //Countries if given in the query
		}
		if (!empty(($points = $queryMeta['params']['points']))) {
			$points = array_map(fn($p) => str_replace(['_HYDRO', '_METEO'], '', $p), $points);
			sort($points);
			$filenameParts[] = implode('-', array_unique([reset($points), end($points)])); //First and last point code if given in the query
		}
		//Start and end times
		$dateReplacePattern = '/[^0-9]/';
		$filenameParts[] = preg_replace($dateReplacePattern, '', $queryMeta['startTime']) . '-' . preg_replace($dateReplacePattern, '', $queryMeta['endTime']);
		$filename = implode('_', $filenameParts);

		return substr($filename, 0, 256 - strlen('.xlsx'));
	}


}
