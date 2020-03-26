<?php

namespace Environet\Sys\Xml;

use Environet\Sys\Xml\Model\ErrorXmlData;
use SimpleXMLElement;

/**
 * Class CreateErrorXml
 *
 * Create Error response XML based on array of ErrorXmlData data models
 *
 * @package Sys\Xml
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class CreateErrorXml {


	/**
	 * Generate error Xml based on xml data object
	 *
	 * @param array|ErrorXmlData[] $errors
	 *
	 * @return SimpleXMLElement
	 */
	public function generateXml(array $errors): SimpleXMLElement {
		//Create xml root
		$xmlNamespaces = 'xmlns:environet="environet"';
		$xmlHeader = '<?xml version="1.0" encoding="UTF-8"?><environet:ErrorResponse '.$xmlNamespaces.'></environet:ErrorResponse>';
		$xml = new SimpleXMLElement($xmlHeader);

		//Add Error elements for each error with code and message
		foreach ($errors as $errorKey => $error) {
			if (!($error instanceof ErrorXmlData)) {
				continue;
			}
			$xmlError = $xml->addChild('Error');
			$xmlError->addChild('ErrorCode', $error->getCode());
			$xmlError->addChild('ErrorMessage', $error->getMessage());
		}

		return $xml;
	}


}
