<?php


namespace Environet\Sys\Xml;

use Environet\Sys\Xml\Model\OutputXmlData;
use Environet\Sys\Xml\Model\OutputXmlObservationMember;
use Exception;
use SimpleXMLElement;

/**
 * Class CreateOutputXml
 *
 * @package   Environet\Sys\Xml
 * @author    SRG Group <dev@srg.hu>
 * @copyright 2020 SRG Group Kft.
 */
class CreateOutputXml {

	/**
	 * @var SimpleXMLElement
	 */
	private $outputXml;


	/**
	 * CreateOutputXml constructor.
	 */
	public function __construct() {
		$this->outputXml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><wml2:Collection xmlns:wml2="http://www.opengis.net/waterml/2.0" xmlns:gml="http://www.opengis.net/gml/3.2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:om="http://www.opengis.net/om/2.0" xmlns:sa="http://www.opengis.net/sampling/2.0" xmlns:sams="http://www.opengis.net/samplingSpatial/2.0" xmlns:xlink="http://www.w3.org/1999/xlink" xsi:schemaLocation="http://www.opengis.net/waterml/2.0 http://schemas.opengis.net/waterml/2.0/waterml2.xsd http://www.opengis.net/gml/3.2 http://schemas.opengis.net/gml/3.2.1/gml.xsd http://www.opengis.net/om/2.0 http://schemas.opengis.net/om/2.0/observation.xsd http://www.opengis.net/sampling/2.0 http://schemas.opengis.net/sampling/2.0/samplingFeature.xsd http://www.opengis.net/samplingSpatial/2.0 http://schemas.opengis.net/samplingSpatial/2.0/spatialSamplingFeature.xsd"></wml2:Collection>');
	}


	/**
	 * @param $data
	 *
	 * @return SimpleXMLElement
	 * @throws Exception
	 */
	public function generateXml($data): SimpleXMLElement {
		$result = new OutputXmlData();
		foreach ($data as $point) {
			$result->addObservationMember(new OutputXmlObservationMember($point));
		}

		$result->render($this->outputXml);

		return $this->outputXml;
	}


}
