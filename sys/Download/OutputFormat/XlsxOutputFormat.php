<?php

namespace Environet\Sys\Download\OutputFormat;

use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Response;
use PDO;
use XLSXWriter;

/**
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
				['select' => 'point.vertical_reference as vertical_reference', 'label' => 'Vertical reference', 'type' => 'string'],
				['select' => 'point.altitude', 'label' => 'Altitude', 'type' => '0.0'],
				['select' => 'river_basin.name as subbasin', 'label' => 'Sub-basin', 'type' => 'string'],
				['select' => 'operator.name as operator_name', 'label' => 'Operator', 'type' => 'string'],
			],
		],
		'data_header_types'       => [
			'Station code' => 'string',
			'Time'         => 'YYYY-MM-DD HH:MM',
		],
		'properties_header_types' => [
			'Symbol'      => 'string',
			'Type'        => 'string',
			'Unit'        => 'string',
			'Description' => 'string',
		],

		//Data (enum) mapping
		'label_map' => [
			'property_type' => [
				PROPERTY_TYPE_REALTIME  => '',
				PROPERTY_TYPE_PROCESSED => '',
			],
		],

		'data_column_type'  => '0.00',
		'data_column_width' => 14,

		// Excel options
		'station_sheet_options'    => [
			'freeze_rows'    => 1,
			'freeze_columns' => 1,
			'auto_filter'    => false,
			'widths'         => [22, 8, 15, 25, 10, 10, 15, 10, 10, 10, 15, 24, 80],
		],
		'data_sheet_options'       => [
			'freeze_rows'    => 1,
			'freeze_columns' => 1,
			'auto_filter'    => false,
			'widths'         => [22, 20],
		],
		'properties_sheet_options' => [
			'widths' => [20, 24, 8, 40],
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
		$this->config['label_map']['property_type'][PROPERTY_TYPE_REALTIME] = $this->globalConfig->getExportPropertyTypeLabelRealTime();
		$this->config['label_map']['property_type'][PROPERTY_TYPE_PROCESSED] = $this->globalConfig->getExportPropertyTypeLabelProcessed();
	}


	public function outputResults(Select $select, array $queryMeta): Response {
		if (is_string($this->globalConfig->getExportTitle()) && !empty($this->globalConfig->getExportTitle())) {
			$this->writer->setTitle($this->globalConfig->getExportTitle());
		}
		if (is_string($this->globalConfig->getExportAuthor()) && !empty($this->globalConfig->getExportAuthor())) {
			$this->writer->setAuthor($this->globalConfig->getExportAuthor());
		}

		//Determine the eucd field based on the type of query
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
			$this->writer->writeSheetHeader(
				'Stations',
				array_combine(
					array_map(static fn($colData) => $colData['label'], $this->config['station_columns'][$queryMeta['type']]),
					array_map(static fn($colData) => $colData['type'], $this->config['station_columns'][$queryMeta['type']])
				),
				$this->config['station_sheet_options']
			);
			foreach ($stationData as $station) {
				$this->writer->writeSheetRow('Stations', $station);
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
				$this->writer->writeSheetHeader($station['station_code'], $dataHeaderType, $dataSheetOptions);
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
			$this->writer->writeSheetHeader($defaultDataSheetName, $dataHeaderType, $dataSheetOptions);
		}

		//Instead of building a large array in memory, we will iterate through the results ordered by time, property, station.
		//This should be more memory-efficient, especially for large datasets.
		//But in tables properties are in columns. Because of this we need to keep track of the current row, and write it when we encounter a new time or station.
		//With this approach, we will have to loop through the results only once, and write rows and columns as we go.

		//Reorder the select query to order result_time first, then property_symbol, then station code.
		$select->clearOrderBy()->orderBy('result_time')->orderBy('property_symbol')->orderBy($stationCodeField);
		$stmt = $select->createStatement();

		//Build a default row array with null values for all properties
		$defaultRowArray = array_fill_keys(['station_code', 'time', ...array_map(static fn($p) => $p['symbol'], $propertyData)], null);
		$rowData = $defaultRowArray;
		while ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
			//Reset the row data to default values
			$rowData = $defaultRowArray;

			if ($groupByStation) {
				//If data is grouped by station, get the correct sheet for the current station
				$sheet = &$sheets[$result[$stationCodeField]];
			} else {
				//Otherwise, use the single default sheet
				$sheet = &$sheets[$defaultDataSheetName];
			}

			//Update the row data with the current result
			$rowData['time'] = $result['result_time'];
			$rowData['station_code'] = $result[$stationCodeField];
			$rowData[$result['property_symbol']] = $result['result_value'];

			if (
				isset($sheet)
				&& isset($rowData)
				&& ($result['result_time'] !== $sheet['timePointer'] || $result[$stationCodeField] !== $sheet['stationCodePointer'])
			) {
				//If we have a row, and the time or station code has changed, write the current row (for the previous time/station) to the sheet.
				$this->writer->writeSheetRow($sheet['name'], $rowData);

				$sheet['rowCount']++;
				if ($sheet['rowCount'] >= $this->config['max_sheet_rows']) {
					//If we have reached the maximum number of rows for a sheet, create a new sheet with an incremented name
					$currentNumber = preg_match('/_(\d+)$/', $sheet['name'], $m) ? (int) $m[1] : 1;
					$sheet['name'] = $sheet['baseName'] . '_' . ($currentNumber + 1);

					$this->writer->writeSheetHeader($sheet['name'], $dataHeaderType, $dataSheetOptions);
					$sheet['rowCount'] = 1; //Reset row count for new sheet (calculate from 1 because of header row)
				}
			}

			//Update the time and station code pointers for the current sheet
			$sheet['timePointer'] = $result['result_time'];
			$sheet['stationCodePointer'] = $result[$stationCodeField];
		}

		$filename = $this->generateFilename($propertySymbols, $queryMeta);

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
