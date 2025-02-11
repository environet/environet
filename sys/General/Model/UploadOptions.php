<?php

namespace Environet\Sys\General\Model;

use Environet\Sys\Upload\Exceptions\UploadException;
use SimpleXMLElement;

/**
 * Class UploadOptions
 *
 * Model class for upload options from the XML.
 *
 * @package Environet\Sys\General\Model
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class UploadOptions {

	/**
	 * Flag to ignore undefined points coming from the upload.
	 * @var bool
	 */
	protected bool $ignoreUndefinedPoints = false;


	/**
	 * @throws UploadException
	 */
	public function initFromXml(?SimpleXMLElement $parsedXml = null): UploadOptions {
		if ($parsedXml !== null) {
			//Set values from XML. If not set, default values will be used
			$uploadOptions = $parsedXml->xpath('/environet:UploadData/environet:UploadOptions')[0] ?? null;
			foreach ($uploadOptions->xpath('*') as $option) {
				$optionName = lcfirst($option->getName());
				if (!property_exists($this, $optionName)) {
					throw new UploadException(408);
				}
				$defaultValue = $this->{$optionName};
				$optionValue = (string) $option;
				//Cast to the type of the default value
				switch (gettype($defaultValue)) {
					case 'boolean':
						$optionValue = filter_var($optionValue, FILTER_VALIDATE_BOOLEAN);
						break;
					default:
						break;
				}

				$this->{$optionName} = $optionValue;
			}
		}

		return $this;
	}


	/**
	 * @return bool
	 */
	public function isIgnoreUndefinedPoints(): bool {
		return $this->ignoreUndefinedPoints;
	}


	/**
	 * @param bool $ignoreUndefinedPoints
	 *
	 * @return UploadOptions
	 */
	public function setIgnoreUndefinedPoints(bool $ignoreUndefinedPoints): UploadOptions {
		$this->ignoreUndefinedPoints = $ignoreUndefinedPoints;

		return $this;
	}


}
