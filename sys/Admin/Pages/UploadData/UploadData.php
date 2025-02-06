<?php


namespace Environet\Sys\Admin\Pages\UploadData;

use Environet\Sys\Config;
use Environet\Sys\General\Exceptions\HttpBadRequestException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class UploadData
 *
 * Admin page for uploading processed and missing data. It accepts multiple csv files, process it, and send the the upload api endpoint.
 *
 * @package Environet\Sys\Admin\Pages\UploadData
 * @author  SRG Group <dev@srg.hu>
 */
class UploadData extends AbstractUploadDataPage {


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
		//Get limits from config
		$maxFiles = ini_get('max_file_uploads');
		$maxSize = Config::getInstance()->getUploadMaxSize();
		$timezoneOptions = array_filter(array_unique(array_map(function ($option) {
			return trim($option);
		}, explode(',', Config::getInstance()->getUploadAvailableTimezones()))));
		$selectedTimezoneOption = null;

		if ($this->request->isPost()) {
			$selectedTimezoneOption = $this->request->getCleanData()['timezone_selector'] ?? null;

			if (array_key_exists('xml_file', $this->request->getCleanData())) {
				//Step 2 - do upload
				$fileResponses = $this->handleSend($this->request->getCleanData()['xml_file']);

				return $this->render('upload_data_success.phtml', compact(
					'fileResponses',
					'maxFiles',
					'maxSize',
					'timezoneOptions',
					'selectedTimezoneOption'
				));
			} else {
				//Step 1 - statistics
				$fileResponses = $this->handleStatistics();

				if (!empty($fileResponses)) {
					$hasErrors = false;
					foreach ($fileResponses as $fileResponse) {
						if ($fileResponse->hasErrors()) {
							$hasErrors = true;
						}
					}

					return $this->render('upload_data_statistics.phtml', compact(
						'hasErrors',
						'fileResponses',
						'maxFiles',
						'maxSize',
						'timezoneOptions',
						'selectedTimezoneOption'
					));
				}
			}
		}

		// Render the form
		return $this->render('/upload_data.phtml', compact('maxFiles', 'maxSize', 'timezoneOptions', 'selectedTimezoneOption'));
	}


	/**
	 * @inheritDoc
	 */
	protected function getCsvFileDir(): string {
		return SRC_PATH . '/data/upload_data_csv';
	}


	/**
	 * @inheritDoc
	 */
	protected function getXmlFileDir(): string {
		return SRC_PATH . '/data/upload_data_xml';
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
	protected function getUploadAllPermission(): string {
		return 'admin.uploadData.upload';
	}


}