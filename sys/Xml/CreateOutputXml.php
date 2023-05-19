<?php


namespace Environet\Sys\Xml;

use Environet\Sys\Xml\Model\OutputXmlData;
use Environet\Sys\Xml\Model\OutputXmlObservationMember;
use Exception;
use SimpleXMLElement;

/**
 * Class CreateOutputXml
 *
 * Create an XML file base on the WaterML schema, which will be served in the download API.
 *
 * @package   Environet\Sys\Xml
 * @author    SRG Group <dev@srg.hu>
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
	 * Generate output XML.
	 * Creates an instance of {@see OutputXmlData}, maps the input data to monitoring point - observation property combination values
	 * and creates {@see OutputXmlObservationMember} instances based on them. Finally it attaches the members to the result xml and renders the whole tree.
	 *
	 * @param       $data
	 * @param array $queryMeta
	 *
	 * @return SimpleXMLElement
	 * @throws Exception
	 * @see OutputXmlData
	 * @see OutputXmlObservationMember
	 */
	public function generateXml($data, array $queryMeta): string {
		$result = new OutputXmlData([], $queryMeta);
		$members = [];

		// Group value rows by mpoint and property - these will be rendered as observation members
		foreach ($data as $valueRow) {
			$memberKey = "{$valueRow['mpoint_id']}_{$valueRow['property_id']}";
			if (!isset($members[$memberKey])) {
				$members[$memberKey] = [];
			}
			$members[$memberKey][] = $valueRow;
		}

		foreach ($members as $valueRows) {
			// Create observation member, use the first valueRow for member data. All other rows will differ only in values and dates.
			$result->addObservationMember(new OutputXmlObservationMember(reset($valueRows), $valueRows, $queryMeta));
		}

		$result->render($this->outputXml);

		return preg_replace_callback('/<([^\s>]+)([^>]*)>/uim', function ($tagMatch) {
			if ($tagMatch[1] === 'wml2:Collection') {
				return $tagMatch[0];
			}

			$attributes = preg_replace_callback('/\w+:\w+="[^"]+"/uim', function ($attributeMatch) {
				if (strpos($attributeMatch[0], 'xmlns:') === 0) {
					return '';
				}

				return $attributeMatch[0];
			}, $tagMatch[2]);

			return '<' . $tagMatch[1] . $attributes . '>';
		}, $this->outputXml->asXML());
	}


}
