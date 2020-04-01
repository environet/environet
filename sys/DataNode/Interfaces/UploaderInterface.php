<?php

namespace Environet\Sys\DataNode\Interfaces;

/**
 * Interface UploaderInterface
 *
 * Interface for required functions of uploader plugins
 *
 * @package   Environet\Sys\DataNode\Interfaces
 * @author    SRG Group <dev@srg.hu>
 */
interface UploaderInterface {


	/**
	 * Do upload action.
	 *
	 * DoUpload is a mandatory custom function that's responsible for triggering actual upload procedures. This has to be written by the user.
	 * This is called automatically every time an upload task is scheduled.
	 *
	 * @return bool true on success, false otherwise.
	 */
	public function doUpload();


	/**
	 * Validate observation data.
	 *
	 * ValidateDiscreteObservation is a function that has to contain a clear logic to decide
	 * whether the data to be uploaded by this uploader is indeed valid, before sending it.
	 *
	 * @param string $data The input format is the raw data to be uploaded by the module itself
	 * @return bool should be true or false boolean
	 */
	public function validateDiscreteObservation($data): bool;


}
