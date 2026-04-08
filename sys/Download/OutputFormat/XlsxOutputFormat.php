<?php

namespace Environet\Sys\Download\OutputFormat;

use Environet\Sys\General\Response;
use XLSXWriter;

/**
 * This class is responsible for generating an XLSX file from the given results and query metadata.
 */
class XlsxOutputFormat extends AbstractTableOutputFormat {


	protected array $config = [
		// Excel column titles and data types
		'station_columns'    => [
			'hydro' => [
				'station_code'   => ['type' => 'string'],
				'country'        => ['type' => 'string'],
				'national_code'  => ['type' => 'string'],
				'name'           => ['type' => 'string'],
				'latitude'       => ['type' => '0.0000'],
				'longitude'      => ['type' => '0.0000'],
				'river'          => ['type' => 'string'],
				'river_km'       => ['type' => '0.0'],
				'catchment_area' => ['type' => '0.0'],
				'gauge_zero'     => ['type' => '0.0'],
				'vertical_ref'   => ['type' => 'string'],
				'subbasin'       => ['type' => 'string'],
				'operator'       => ['type' => 'string'],
			],
			'meteo' => [
				'station_code'  => ['type' => 'string'],
				'country'       => ['type' => 'string'],
				'national_code' => ['type' => 'string'],
				'name'          => ['type' => 'string'],
				'latitude'      => ['type' => '0.0000'],
				'longitude'     => ['type' => '0.0000'],
				'vertical_ref'  => ['type' => 'string'],
				'altitude'      => ['type' => '0.0'],
				'subbasin'      => ['type' => 'string'],
				'operator'      => ['type' => 'string'],
			],
		],
		'data_headers'       => [
			'station_code' => ['type' => 'string'],
			'time'         => ['type' => 'YYYY-MM-DD HH:MM'],
		],
		'properties_headers' => [
			'symbol'      => ['type' => 'string'],
			'type'        => ['type' => 'string'],
			'unit'        => ['type' => 'string'],
			'description' => ['type' => 'string'],
		],

		'data_column_type'         => '0.00',
		'data_column_width'        => 14,

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

		'max_rows'  => 1048576, // Fixed limit of MS Excel
		'extension' => 'xlsx',
	];

	protected XLSXWriter $writer;


	public function __construct() {
		parent::__construct();
		$this->writer = new XLSXWriter();

		$this->config = array_replace_recursive($this->defaultConfig, $this->config);
	}


	protected function initializeWriter(): void {
		if (is_string($this->globalConfig->getExportTitle()) && !empty($this->globalConfig->getExportTitle())) {
			$this->writer->setTitle($this->globalConfig->getExportTitle());
		}
		if (is_string($this->globalConfig->getExportAuthor()) && !empty($this->globalConfig->getExportAuthor())) {
			$this->writer->setAuthor($this->globalConfig->getExportAuthor());
		}
	}


	protected function writeHeader(string $sheetName, array $columnsConfig) {
		switch ($sheetName) {
			case 'Stations':
				$options = $this->config['station_sheet_options'];
				break;
			case 'Properties':
				$options = $this->config['properties_sheet_options'];
				break;
			default:
				$options = $this->config['data_sheet_options'];
				$options['widths'] = array_merge(
					$options['widths'],
					array_fill(0, count($columnsConfig) - 2, $this->config['data_column_width'])
				);
		}
		$this->writer->writeSheetHeader(
			$sheetName,
			array_combine(
				array_map(static fn($colData) => $colData['label'], $columnsConfig),
				array_map(static fn($colData) => $colData['type'], $columnsConfig)
			),
			$options
		);
	}


	protected function writeRow(string $sheetName, array $rowData, array $columnsConfig) {
		$this->writer->writeSheetRow($sheetName, $rowData);
	}


	protected function finalizeWrite(Response $response) {
		//Write the file to a string, and send it as a response
		$content = $this->writer->writeToString();
		$size = strlen((string) $content);

		$response->setContent($content);
		$response->addHeader('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
			->addHeader('Content-Length: ' . $size);
	}


}
