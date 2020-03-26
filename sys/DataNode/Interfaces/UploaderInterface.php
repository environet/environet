<?php

namespace Environet\Sys\DataNode\Interfaces;

/**
 * Class UploaderInterface
 *
 * Interface for required functions of uploader plugins
 *
 * @author Ádám Bálint <adam.balint@srg.hu>
 * @package Environet\Sys
 */
interface UploaderInterface {


	/**
	 * DoUpload is a mantatory custom function that's responsible for triggering actual upload
	 * procedures. This has to be written by the user.
	 *
	 * This is called automatically every time an upload task is scheduled.
	 *
	 * @return bool true on success, false otherwise.
	 */
	public function doUpload();


	/**
	 * ValidateDiscreteObservation is a function that has to contain a clear logic to decide
	 * whether the data to be uploaded by this uploader is indeed valid, before sending it.
	 *
	 * @param string $data The input format is the raw data to be uploaded by the module itself
	 * @return bool should be true or false boolean
	 */
	public function validateDiscreteObservation($data): bool;


}
