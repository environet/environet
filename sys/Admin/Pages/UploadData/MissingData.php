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
	protected function getUploadAllPermission(): string {
		return 'admin.missingData.upload';
	}


}