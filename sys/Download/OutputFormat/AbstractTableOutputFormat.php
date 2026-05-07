<?php

namespace Environet\Sys\Download\OutputFormat;

use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Response;
use PDO;

/**
 * Abstract base class for table-based output formats (XLSX, CSV)
 * Contains shared configuration and logic for formats that output data in tabular form
 */
abstract class AbstractTableOutputFormat extends AbstractOutputFormat {


	/**
	 * Shared configuration for all table-based formats
	 */
	protected array $defaultConfig = [
		'default_data_sheet_name' => 'Results', //Default sheet name

		// Column definitions for stations (select queries and labels)
		// Each column has a unique slug ID as key for easy overriding/merging
		// Column types are defined in sub classes with the same array structure
		'station_columns'         => [
			'hydro' => [
				'station_code'   => ['select' => 'point.eucd_wgst as station_code', 'label' => 'International code'],
				'country'        => ['select' => 'point.country', 'label' => 'Country'],
				'national_code'  => ['select' => 'point.ncd_wgst as national_code', 'label' => 'National code'],
				'name'           => ['select' => 'point.name station_name', 'label' => 'Name'],
				'latitude'       => ['select' => 'point.lat', 'label' => 'Latitude'],
				'longitude'      => ['select' => 'point.long', 'label' => 'Longitude'],
				'river'          => ['select' => 'river.cname as river', 'label' => 'River'],
				'river_km'       => ['select' => 'point.river_kilometer', 'label' => 'River-km'],
				'catchment_area' => ['select' => 'point.catchment_area', 'label' => 'Catchment area (km²)'],
				'gauge_zero'     => ['select' => 'point.gauge_zero', 'label' => 'Gauge zero (m)'],
				'vertical_ref'   => ['select' => 'point.vertical_reference', 'label' => 'Vertical reference'],
				'subbasin'       => ['select' => 'river_basin.name as subbasin', 'label' => 'Sub-basin'],
				'operator'       => ['select' => 'operator.name as operator_name', 'label' => 'Operator'],
			],
			'meteo' => [
				'station_code'  => ['select' => 'point.eucd_pst as station_code', 'label' => 'International code'],
				'country'       => ['select' => 'point.country', 'label' => 'Country'],
				'national_code' => ['select' => 'point.ncd_pst as national_code', 'label' => 'National code'],
				'name'          => ['select' => 'point.name station_name', 'label' => 'Name'],
				'latitude'      => ['select' => 'point.lat', 'label' => 'Latitude'],
				'longitude'     => ['select' => 'point.long', 'label' => 'Longitude'],
				'vertical_ref'  => ['select' => 'point.vertical_reference as vertical_reference', 'label' => 'Vertical reference'],
				'altitude'      => ['select' => 'point.altitude', 'label' => 'Altitude'],
				'subbasin'      => ['select' => 'river_basin.name as subbasin', 'label' => 'Sub-basin'],
				'operator'      => ['select' => 'operator.name as operator_name', 'label' => 'Operator'],
			],
		],

		// Headers for data tables
		'data_headers'            => [
			'station_code' => ['label' => 'Station code'],
			'time'         => ['label' => 'Time (UTC)'],
		],

		// Headers for properties metadata
		'properties_headers'      => [
			'symbol'      => ['label' => 'Symbol'],
			'type'        => ['label' => 'Type'],
			'unit'        => ['label' => 'Unit'],
			'description' => ['label' => 'Description'],
		],

		'data_column_type' => '',

		// Label mappings for enum values
		'label_map'        => [
			'property_type' => [
				PROPERTY_TYPE_REALTIME  => '',
				PROPERTY_TYPE_PROCESSED => '',
			],
		],

		// Maximum rows per sheet/file
		'max_rows'         => INF, // Default row limit is not set
		'extension'        => '' //Extension of output file
	];

	/**
	 * Options for all table-based formats
	 */
	protected array $options = [
		'group_by_station'     => false, // Put each station into a separate sheet/file
		'add_stations_sheet'   => true,  // Add stations metadata
		'add_properties_sheet' => true, // Add properties metadata
	];

	protected array $config = [];


	public function __construct() {
		parent::__construct();
		// Initialize label mappings from global config
		$this->defaultConfig['label_map']['property_type'][PROPERTY_TYPE_REALTIME] = $this->globalConfig->getExportPropertyTypeLabelRealTime();
		$this->defaultConfig['label_map']['property_type'][PROPERTY_TYPE_PROCESSED] = $this->globalConfig->getExportPropertyTypeLabelProcessed();
	}


	/**
	 * Initialize the writer service, or config
	 */
	abstract protected function initializeWriter(): void;


	/**
	 * Write the header row of a sheet
	 */
	abstract protected function writeHeader(string $sheetName, array $columnsConfig);


	/**
	 * Write a data row to a sheet.
	 */
	abstract protected function writeRow(string $sheetName, array $rowData, array $columnsConfig);


	/**
	 * Finalize the writer with set some properties in response
	 */
	abstract protected function finalizeWrite(Response $response);


	public function outputResults(Select $select, array $queryMeta): Response {
		$this->initializeWriter();

		//Determine the EUCD field based on the type of query
		switch ($queryMeta['type']) {
			case 'hydro':
				$stationCodeField = 'eucd_wgst';

				break;
			case 'meteo':
				$stationCodeField = 'eucd_pst';

				break;
		}

		//Get station data
		$selectColumns = array_map(static fn($colData) => $colData['select'], $this->config['station_columns'][$queryMeta['type']]);
		$stationData = $this->getStationData($select, $queryMeta, $selectColumns);
		if ($this->options['add_stations_sheet']) {
			//Write the station sheet if the option is enabled
			$this->writeHeader('Stations', $this->config['station_columns'][$queryMeta['type']]);
			foreach ($stationData as $station) {
				$this->writeRow('Stations', $station, $this->config['station_columns'][$queryMeta['type']]);
			}
		}

		// Get property data
		$propertyData = $this->getPropertyData($select, $queryMeta, [
			'observed_property.symbol',
			'observed_property.type',
			'observed_property.unit',
			'observed_property.description',
		]);
		$propertySymbols = array_column($propertyData, 'symbol');
		if ($this->options['add_properties_sheet']) {
			//Write the properties sheet if the option is enabled
			$this->writeHeader('Properties', $this->config['properties_headers']);
			foreach ($propertyData as $property) {
				$property['type'] = strtr((string) $property['type'], $this->config['label_map']['property_type']);
				$this->writeRow('Properties', $property, $this->config['properties_headers']);
			}
		}

		//Check if we need to group by station
		$groupByStation = $this->options['group_by_station'];
		$this->config['data_sheet_options']['auto_filter'] = !$groupByStation;

		//Build data sheet header. Add default columns, and columns for each property
		$dataHeadersConfig = $this->config['data_headers'];
		foreach ($propertyData as $property) {
			$dataHeadersConfig[$property['symbol']] = ['label' => $property['symbol'], 'type' => $this->config['data_column_type']];
		}

		$defaultDataSheetName = $this->config['default_data_sheet_name'];

		$sheets = [];
		if ($groupByStation) {
			//Create a sheet for each station, write headers. Sheets are created, and a pointer to each sheet is stored in the $sheets array
			foreach ($stationData as $station) {
				$sheets[$station['station_code']] = [
					'baseName'           => $station['station_code'],
					'name'               => $station['station_code'],
					'rowCount'           => 1, //Start at 1 because of header row
					'timePointer'        => null,
					'stationCodePointer' => null,
				];
				$this->writeHeader($station['station_code'], $dataHeadersConfig);
			}
		} else {
			//Create a single sheet for all data. Sheets array also created for consistency
			$sheets = [
				$defaultDataSheetName => [
					'baseName'           => $defaultDataSheetName,
					'name'               => $defaultDataSheetName,
					'rowCount'           => 1, //Start at 1 because of header row
					'timePointer'        => null,
					'stationCodePointer' => null,
				],
			];
			$this->writeHeader($defaultDataSheetName, $dataHeadersConfig);
		}

		//Instead of building a large array in memory, we will iterate through the results ordered by time, property, station.
		//This should be more memory-efficient, especially for large datasets.
		//But in tables properties are in columns. Because of this we need to keep track of the current row, and write it when we encounter a new time or station.
		//With this approach, we will have to loop through the results only once, and write rows and columns as we go.

		//Reorder the select query to order results appropriately
		//If grouping by station, order by station first to keep all data for one station together
		//Otherwise, order by time first for chronological output
		$select->clearOrderBy();
		if ($groupByStation) {
			$select->orderBy($stationCodeField)->orderBy('result_time')->orderBy('property_symbol');
		} else {
			$select->orderBy('result_time')->orderBy($stationCodeField)->orderBy('property_symbol');
		}
		$stmt = $select->createStatement();

		//Build a default row array with null values for all properties
		$defaultRowArray = array_fill_keys(['station_code', 'time', ...array_map(static fn($p) => $p['symbol'], $propertyData)], null);
		$rowData = $defaultRowArray;

		// Track previous values to detect changes
		$prevSheet = null;

		// Helper closure to write a row and handle sheet overflow
		$writeCurrentRow = function (array &$sheet, array $rowData) use ($dataHeadersConfig) {
			$this->writeRow($sheet['name'], $rowData, $dataHeadersConfig);

			$sheet['rowCount']++;
			if ($sheet['rowCount'] >= $this->config['max_rows']) {
				//If we have reached the maximum number of rows for a sheet, create a new sheet with an incremented name
				$currentNumber = preg_match('/_(\d+)$/', $sheet['name'], $m) ? (int) $m[1] : 1;
				$sheet['name'] = $sheet['baseName'] . '_' . ($currentNumber + 1);

				$this->writeHeader($sheet['name'], $dataHeadersConfig);
				$sheet['rowCount'] = 1; //Reset row count for new sheet (calculate from 1 because of header row)
			}
		};

		while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
			if ($groupByStation) {
				//If data is grouped by station, get the correct sheet for the current station
				$sheet = &$sheets[$result[$stationCodeField]];
			} else {
				//Otherwise, use the single default sheet
				$sheet = &$sheets[$defaultDataSheetName];
			}

			//Check if time or station has changed, if so, write the previous row first
			//We use $prevSheet to check, because when station changes, $sheet is already pointing to the new sheet
			if (
				$prevSheet !== null
				&& isset($prevSheet['timePointer'])
				&& isset($prevSheet['stationCodePointer'])
				&& ($result['result_time'] !== $prevSheet['timePointer'] || $result[$stationCodeField] !== $prevSheet['stationCodePointer'])
			) {
				//If we have a row, and the time or station code has changed, write the previous row to the sheet.
				$writeCurrentRow($prevSheet, $rowData);

				//Reset the row data to default values
				$rowData = $defaultRowArray;
			}

			//Update the row data with the current result
			$rowData['time'] = $result['result_time'];
			$rowData['station_code'] = $result[$stationCodeField];
			$rowData[$result['property_symbol']] = $result['result_value'];

			//Update the time and station code pointers for the current sheet
			$sheet['timePointer'] = $result['result_time'];
			$sheet['stationCodePointer'] = $result[$stationCodeField];

			//Remember current sheet for next iteration
			$prevSheet = &$sheet;
		}

		// Write the last row after the loop ends
		// The $prevSheet variable points to the last processed sheet
		if (isset($rowData) && isset($rowData['time']) && isset($prevSheet)) {
			$writeCurrentRow($prevSheet, $rowData);
		}

		$filename = $this->generateFilename($propertySymbols, $queryMeta);

		$response = new Response();
		$response->addHeader('Content-Disposition: attachment; filename="' . $filename . '.' . $this->config['extension'] . '"');

		$this->finalizeWrite($response);

		return $response;
	}


}



