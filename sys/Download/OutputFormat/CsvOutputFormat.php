<?php

namespace Environet\Sys\Download\OutputFormat;

use DateTime;
use Environet\Sys\General\Response;
use Exception;
use RuntimeException;
use ZipArchive;

/**
 * This class is responsible for generating an ZIP file including csv files from the given results and query metadata.
 */
class CsvOutputFormat extends AbstractTableOutputFormat {


	protected array $config = [
		// Data types for columns
		'station_columns'    => [
			'hydro' => [
				'station_code'   => ['type' => 'string'],
				'country'        => ['type' => 'string'],
				'national_code'  => ['type' => 'string'],
				'name'           => ['type' => 'string'],
				'latitude'       => ['type' => 'number;4'],
				'longitude'      => ['type' => 'number;4'],
				'river'          => ['type' => 'string'],
				'river_km'       => ['type' => 'number;1'],
				'catchment_area' => ['type' => 'number;1'],
				'gauge_zero'     => ['type' => 'number;1'],
				'vertical_ref'   => ['type' => 'string'],
				'subbasin'       => ['type' => 'string'],
				'operator'       => ['type' => 'string'],
			],
			'meteo' => [
				'station_code'  => ['type' => 'string'],
				'country'       => ['type' => 'string'],
				'national_code' => ['type' => 'string'],
				'name'          => ['type' => 'string'],
				'latitude'      => ['type' => 'number;4'],
				'longitude'     => ['type' => 'number;4'],
				'vertical_ref'  => ['type' => 'string'],
				'altitude'      => ['type' => 'number;1'],
				'subbasin'      => ['type' => 'string'],
				'operator'      => ['type' => 'string'],
			],
		],
		'data_headers'       => [
			'station_code' => ['type' => 'string'],
			'time'         => ['type' => 'date;Y-m-d H:i'],
		],
		'properties_headers' => [
			'symbol'      => ['type' => 'string'],
			'type'        => ['type' => 'string'],
			'unit'        => ['type' => 'string'],
			'description' => ['type' => 'string'],
		],

		'data_column_type' => 'number;2',

		'extension' => 'zip',
	];

	protected string $tmpDirPath;

	protected array $filePointers = [];


	public function __construct() {
		parent::__construct();
		//Merge config with the default config
		$this->config = array_replace_recursive($this->defaultConfig, $this->config);
	}


	/**
	 * Create a directory where csv files (which represents sheets) are stored
	 */
	protected function initializeWriter(): void {
		$tmpDirPath = SRC_PATH . '/data/tmp/output' . time() . '_' . uniqid();
		if (!is_dir($tmpDirPath)) {
			mkdir($tmpDirPath, 0755, true);
		}
		$this->tmpDirPath = $tmpDirPath;
	}


	/**
	 * Write header row in a csv file
	 */
	protected function writeHeader(string $sheetName, array $columnsConfig) {
		$data = [];
		foreach ($columnsConfig as $columnConfig) {
			$data[] = $columnConfig['label'] ?? '';
		}
		fputcsv($this->getSheetFile($sheetName), $data, ',', '"', '\\');
	}


	/**
	 * Write a row of data in a csv file. The data is mapped to the correct type based on the column configuration.
	 */
	protected function writeRow(string $sheetName, array $rowData, array $columnsConfig) {
		$columnsConfig = array_values($columnsConfig);
		$rowData = array_values($rowData);
		$data = [];
		foreach ($columnsConfig as $key => $columnConfig) {
			if (!array_key_exists($key, $rowData) || $rowData[$key] === null) {
				//Empty or not set column
				$data[] = '';
			} else {
				//Valid data, format value
				$data[] = $this->mapToType($rowData[$key], $columnConfig['type']);
			}
		}
		fputcsv($this->getSheetFile($sheetName), $data, ',', '"', '\\');
	}


	/**
	 * Create zip file based on csv files
	 */
	protected function finalizeWrite(Response $response): void {
		$filename = 'output.zip';
		foreach ($response->getHeaders() as $header) {
			if (preg_match('/^Content-Disposition:.*filename="([^"]+)".*$/i', $header, $matches)) {
				$filename = $matches[1];
			}
		}
		$zipFilePath = $this->tmpDirPath . '/' . $filename;
		$zipArchive = new ZipArchive();
		$zipArchive->open($zipFilePath, ZipArchive::CREATE);
		foreach ($this->filePointers as $sheetName => $filePointer) {
			fclose($filePointer);
			$filename = $sheetName . '.csv';
			$filePath = $this->tmpDirPath . '/' . $filename;
			$zipArchive->addFile($filePath, $filename);
		}

		// Add LICENSE.txt file if license text is configured
		$licenseText = $this->globalConfig->getLicenseText();
		if (!empty($licenseText)) {
			$licenseFilePath = $this->tmpDirPath . '/LICENSE.txt';
			file_put_contents($licenseFilePath, $licenseText);
			$zipArchive->addFile($licenseFilePath, 'LICENSE.txt');
		}

		$zipArchive->close();

		//Write the file to a string, and send it as a response
		$content = file_get_contents($zipFilePath);
		$size = strlen((string) $content);

		$response->setContent($content);
		$response->addHeader('Content-Type: application/zip')
			->addHeader('Content-Length: ' . $size);
	}


	/**
	 * Every 'sheet' is one csv file, file name is the name of the sheet.
	 * @return resource
	 */
	private function getSheetFile(string $sheetName) {
		$filename = $sheetName . '.csv';
		$filePath = $this->tmpDirPath . '/' . $filename;
		if (!isset($this->filePointers[$filename])) {
			$this->filePointers[$sheetName] = fopen($filePath, 'a+');
			if ($this->filePointers[$sheetName] === false) {
				throw new RuntimeException("Failed to create temporary file for sheet $sheetName");
			}
		}

		return $this->filePointers[$sheetName];
	}


	/**
	 * Format value based on type specification
	 */
	private function mapToType(string $value, string $type): string {
		$typeParts = explode(';', $type);
		$type = $typeParts[0];
		$typeModifier = $typeParts[1] ?? null;
		if ($type === 'string') {
			return $value;
		} elseif ($type === 'number' && is_numeric($typeModifier)) {
			return number_format((float) $value, (int) $typeModifier, '.', '');
		} elseif ($type === 'date' && !empty($typeModifier)) {
			try {
				$parsedDate = new DateTime($value);
			} catch (Exception $e) {
				return $value;
			}

			return $parsedDate->format($typeModifier);
		}

		return $value;
	}


}
