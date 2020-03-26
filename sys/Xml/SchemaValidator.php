<?php


namespace Environet\Sys\Xml;

use DOMDocument;
use Environet\Sys\Xml\Exceptions\SchemaInvalidException;
use Exception;
use SimpleXMLElement;

/**
 * Class SchemaValidator
 *
 * Validate a parsed SimepleXmlElement against an xsd schema
 *
 * @package Environet\Sys\Xml
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class SchemaValidator {

	/**
	 * The parsed XML
	 * @var SimpleXMLElement
	 */
	private $xml;

	/**
	 * File path of the XSD schema
	 * @var string
	 */
	private $schemaFile;


	/**
	 * SchemaValidator constructor.
	 *
	 * @param SimpleXMLElement $xml
	 * @param string           $schemaFile
	 *
	 * @throws Exception
	 */
	public function __construct(SimpleXMLElement $xml, string $schemaFile) {
		$this->xml = $xml;

		//Check if schema exists
		if (!file_exists($schemaFile)) {
			throw new Exception('File doesn\'t exist: '.$schemaFile);
		}
		$this->schemaFile = $schemaFile;
	}


	/**
	 * Validate the xml, and throw an exception on error or warning
	 *
	 * @throws SchemaInvalidException
	 */
	public function validate(): bool {
		libxml_use_internal_errors(true);

		$xml = new DOMDocument();
		$xml->loadXML($this->xml->asXml());

		if (!$xml->schemaValidate($this->schemaFile)) {
			//Create exception from errors
			$errors = libxml_get_errors();
			libxml_clear_errors();
			throw new SchemaInvalidException($errors);
		}

		//Xml is valid
		return true;
	}


}