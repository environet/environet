<?php

namespace Environet\Sys\Download\OutputFormat;

use Environet\Sys\General\Response;
use XLSXWriter;

/**
 * Class XlsxOutputFormat
 *
 * This class is responsible for generating an XLSX file from the given results and query metadata.
 */
class XlsxOutputFormat extends AbstractOutputFormat {

	protected array $config = [
		'default_data_sheet_name' => 'Results', //Default sheet name


		// Excel column titles and data types
		'station_columns'         => [
			'hydro' => [
				['select' => 'point.eucd_wgst as station_code', 'label' => 'International code', 'type' => 'string'],
				['select' => 'point.country', 'label' => 'Country', 'type' => 'string'],
				['select' => 'point.ncd_wgst as national_code', 'label' => 'National code', 'type' => 'string'],
				['select' => 'point.name station_name', 'label' => 'Name', 'type' => 'string'],
				['select' => 'point.lat', 'label' => 'Latitude', 'type' => '0.0000'],
				['select' => 'point.long', 'label' => 'Longitude', 'type' => '0.0000'],
				['select' => 'river.cname as river', 'label' => 'River', 'type' => 'string'],
				['select' => 'point.river_kilometer', 'label' => 'River-km', 'type' => '0.0'],
				['select' => 'point.catchment_area', 'label' => 'Catchment area (km²)', 'type' => '0.0'],
				['select' => 'point.gauge_zero', 'label' => 'Gauge zero (m)', 'type' => '0.0'],
				['select' => 'point.vertical_reference', 'label' => 'Vertical reference', 'type' => 'string'],
				['select' => 'river_basin.name as subbasin', 'label' => 'Sub-basin', 'type' => 'string'],
				['select' => 'operator.name as operator_name', 'label' => 'Operator', 'type' => 'string'],
			],
			'meteo' => [
				['select' => 'point.eucd_pst as station_code', 'label' => 'International code', 'type' => 'string'],
				['select' => 'point.country', 'label' => 'Country', 'type' => 'string'],
				['select' => 'point.ncd_pst as national_code', 'label' => 'National code', 'type' => 'string'],
				['select' => 'point.name station_name', 'label' => 'Name', 'type' => 'string'],
				['select' => 'point.lat', 'label' => 'Latitude', 'type' => '0.0000'],
				['select' => 'point.long', 'label' => 'Longitude', 'type' => '0.0000'],
				['select' => 'point.altitude', 'label' => 'Altitude', 'type' => '0.0'],
				['select' => 'river_basin.name as subbasin', 'label' => 'Sub-basin', 'type' => 'string'],
				['select' => 'operator.name as operator_name', 'label' => 'Operator', 'type' => 'string'],
			]
		],
		'data_header_types'       => [
			'Station code' => 'string',
			'Time'         => 'YYYY-MM-DD HH:MM',
		],
		'properties_header_types' => [
			'Symbol'      => 'string',
			'Type'        => 'string',
			'Unit'        => 'string',
			'Description' => 'string'
		],

		//Data (enum) mapping
		'label_map'               => [
			'property_type' => [
				1 => 'Raw near-real-time data',
				2 => 'Validated processed data'
			]
		],

		'data_column_type'         => '0.00',
		'data_column_width'        => 14,

		// Excel options
		'station_sheet_options'    => [
			'freeze_rows'    => 1,
			'freeze_columns' => 1,
			'auto_filter'    => false,
			'widths'         => [22, 8, 15, 25, 10, 10, 15, 10, 10, 10, 15, 24, 80]
		],
		'data_sheet_options'       => [
			'freeze_rows'    => 1,
			'freeze_columns' => 1,
			'auto_filter'    => false,
			'widths'         => [22, 20]
		],
		'properties_sheet_options' => [
			'widths' => [20, 24, 8, 40]
		],

		'max_sheet_rows' => 1048576, // Fixed limit of MS Excel
	];

	protected array $options = [
		'group_by_station'     => false, // Put each station into a separate sheet
		'add_stations_sheet'   => true, // Add a sheet with station data
		'add_properties_sheet' => true, // Add a sheet with property data
	];

	protected XLSXWriter $writer;


	public function __construct() {
		parent::__construct();
		$this->writer = new XLSXWriter();
	}


	public function outputResults(array $results, array $queryMeta): Response {
		$exportTitle = $exportAuthor = '';
		if (is_string($this->globalConfig->getExportTitle()) && !empty($this->globalConfig->getExportTitle())) {
			$exportTitle = $this->globalConfig->getExportTitle();
		}
		if (is_string($this->globalConfig->getExportAuthor()) && !empty($this->globalConfig->getExportAuthor())) {
			$exportAuthor = $this->globalConfig->getExportAuthor();
		}
		$this->writer->setTitle($exportTitle);
		$this->writer->setAuthor($exportAuthor);

		//Determine the eucd field based on the type of query
		switch ($queryMeta['type']) {
			case 'hydro':
				$stationCodeField = 'eucd_wgst';
				break;
			case 'meteo':
				$stationCodeField = 'eucd_pst';
				break;
		}

		if ($this->options['add_stations_sheet']) {
			//Get station data, and write it to the sheet
			$selectColumns = array_map(fn($colData) => $colData['select'], $this->config['station_columns'][$queryMeta['type']]);
			$stationData = $this->getStationData($results, $queryMeta, $selectColumns);
			$this->writer->writeSheetHeader(
				'Stations',
				array_combine(
					array_map(fn($colData) => $colData['label'], $this->config['station_columns'][$queryMeta['type']]),
					array_map(fn($colData) => $colData['type'], $this->config['station_columns'][$queryMeta['type']])
				),
				$this->config['station_sheet_options']
			);
			foreach ($stationData as $station) {
				$this->writer->writeSheetRow('Stations', $station);
			}
		}

		// Get property data, and write it to the sheet
		$propertyData = $this->getPropertyData($results, $queryMeta, [
			'observed_property.symbol',
			'observed_property.type',
			'observed_property.unit',
			'observed_property.description',
		]);
		$propertySymbols = array_column($propertyData, 'symbol');
		if ($this->options['add_properties_sheet']) {
			$this->writer->writeSheetHeader('Properties', $this->config['properties_header_types'], $this->config['properties_sheet_options']);
			foreach ($propertyData as $property) {
				$property['type'] = strtr($property['type'], $this->config['label_map']['property_type']);
				$this->writer->writeSheetRow('Properties', $property);
			}
		}

		//Check if we need to group by station
		$groupByStation = $this->options['group_by_station'];
		$this->config['data_sheet_options']['auto_filter'] = !$groupByStation;

		//Build data sheet header. Add default columns, and columns for each property
		$dataHeaderType = $this->config['data_header_types'];
		$dataSheetOptions = $this->config['data_sheet_options'];
		foreach ($propertyData as $property) {
			$dataHeaderType[$property['symbol']] = $this->config['data_column_type'];
			$dataSheetOptions['widths'][] = $this->config['data_column_width'];
		}

		//Organize results by station and time. It builds a new multidimensional array with the station code and time as keys
		//Array structure: {station_code: {time: {property_symbol: row_data}}}
		$organizedResults = [];
		//Default row array with null values for each property
		$defaultRowArray = array_fill_keys(['station_code', 'time', ...array_map(fn($p) => $p['symbol'], $propertyData)], null);
		foreach ($results as $result) {
			if (!isset($organizedResults[$result[$stationCodeField]][$result['result_time']])) {
				$organizedResults[$result[$stationCodeField]][$result['result_time']] = array_merge($defaultRowArray, [
					'station_code' => $result[$stationCodeField],
					'time'         => $result['result_time']
				]);
			}
			$organizedResults[$result[$stationCodeField]][$result['result_time']][$result['property_symbol']] = $result['result_value'];
		}

		$sheetName = $this->config['default_data_sheet_name'];
		$sheetNameBase = $sheetName; //Base name for the sheet, used to create new counted sheets
		$rowCount = 0;
		if (!$groupByStation) {
			//One sheet for all stations, write header
			$this->writer->writeSheetHeader($sheetName, $dataHeaderType, $dataSheetOptions);
		}
		//Write data to the sheet
		foreach ($organizedResults as $stationCode => $stationResults) {
			if ($groupByStation) {
				//If we are grouping by station, we need to create a new sheet for each station
				$sheetName = $stationCode; //Sheet name is the station code
				$sheetNameBase = $sheetName; //Base name for the sheet, used to create new counted sheets
				$this->writer->writeSheetHeader($sheetName, $dataHeaderType, $dataSheetOptions);
				$rowCount = 0;
			}
			foreach ($stationResults as $stationResult) {
				//Add the result row to the current sheet
				$this->writer->writeSheetRow($sheetName, $stationResult);
				$rowCount++;

				//Check if row count exceeds the maximum limit. If it does, create a new sheet with a number suffix. New data rows will be written to the new sheet.
				if ($rowCount >= $this->config['max_sheet_rows']) {
					$currentNumber = preg_match('/_(\d+)$/', $sheetName, $m) ? (int) $m[1] : 1;
					$sheetName = $sheetNameBase . '_' . ($currentNumber + 1);

					$this->writer->writeSheetHeader($sheetName, $dataHeaderType, $dataSheetOptions);
					$rowCount = 0;
				}
			}
		}


		// Generate export filename
		$filenameParts = [];
		if ($exportTitle) {
			$filenameParts[] = str_replace(' ', '_', preg_replace('/\W+/iu', '_', $exportTitle)); //Use export title if given
		} //Base name
		$filenameParts[] = implode('-', array_unique([reset($propertySymbols), end($propertySymbols)])); //First and last property symbol
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
		$filename = substr($filename, 0, 256 - strlen('.xlsx'));

		//Write the file to a string, and send it as a response
		$content = $this->writer->writeToString();
		$size = strlen($content);

		$response = new Response();
		$response->setContent($content);
		$response->addHeader('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
			->addHeader('Content-Length: ' . $size)
			->addHeader('Content-Disposition: attachment; filename="' . $filename . '.xlsx"')
			->addHeader('Content-Transfer-Encoding: binary')
			->addHeader('Cache-Control: must-revalidate')
			->addHeader('Pragma: public');

		return $response;
	}


}
