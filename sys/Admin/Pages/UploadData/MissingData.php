<?php


namespace Environet\Sys\Admin\Pages\UploadData;

use Environet\Sys\General\Exceptions\HttpBadRequestException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class MissingData
 *
 * Admin page for uploading missing data. It accepts multiple csv files, process it, and send the the upload api endpoint.
 *
 * @package Environet\Sys\Admin\Pages\UploadData
 * @author  SRG Group <dev@srg.hu>
 */
class MissingData extends AbstractUploadDataPage {


	/**
	 * Handle the upload request.
	 *
	 * If GET, it displays the upload form with some information about size limits
	 * If POST, it creates an XML based on the files, and post it to the upload API.
	 *
	 * @return mixed|void
	 * @throws HttpBadRequestException
	 * @throws RenderException
	 */
	public function handle(): ?Response {
		if ($this->request->isPost()) {
			$this->handlePost();
		}

		//Display limits on upload page
		$maxFiles = ini_get('max_file_uploads');
		$maxSize = ini_get('post_max_size');

		// Render the form
		return $this->render('/missing_data.phtml', compact('maxFiles', 'maxSize'));
	}


	/**
	 * @inheritDoc
	 */
	protected function getFileDir(): string {
		return SRC_PATH . '/data/missing_data_csv';
	}


	/**
	 * @inheritDoc
	 */
	protected function getFileInputName(): string {
		return 'csv';
	}


	/**
	 * @inheritDoc
	 */
	protected function mapCsv($fileHandle): array {
		$mpointId = null;
		$properties = [];
		$propertiesData = [];
		$rowIndex = 0;
		while (($row = fgetcsv($fileHandle, 10000)) !== false) {
			$rowIndex ++;
			if ($rowIndex === 1 && !empty($row[1])) {
				//Get mpoint id from first row
				$mpointId = $row[1];
			}
			if ($rowIndex === 2) {
				//Get properties from row 2. First column will be the date, it's not a property
				$properties = array_slice($row, 1, null, true);
				$propertiesData = array_fill_keys($properties, []);
			}
			if ($rowIndex > 2) {
				//Data rows with dates and values for each property
				foreach ($properties as $propertyKey => $property) {
					if (!(!empty($row[0]) && ($dateTime = date_create($row[0])))) {
						continue;
					}
					$propertiesData[$property][] = [
						'time'  => $dateTime->format('c'),
						'value' => $row[$propertyKey] ? floatval($row[$propertyKey]) : null
					];
				}
			}
		}

		return [$mpointId, $propertiesData];
	}


}