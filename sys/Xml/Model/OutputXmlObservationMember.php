<?php


namespace Environet\Sys\Xml\Model;

use Environet\Sys\Xml\XmlRenderable;
use Exception;
use SimpleXMLElement;

/**
 * Class OutputXmlObservationMember
 *
 * Wrapper class for handling WML observationMember data structure
 *
 * @package Environet\Sys\Xml\Model
 * @author  SRG Group <dev@srg.hu>
 */
class OutputXmlObservationMember implements XmlRenderable {

	/**
	 * @var array
	 */
	private $propertyData;

	/**
	 * @var array
	 */
	private $valueRows;


	/**
	 * OutputXmlObservationMember constructor.
	 *
	 * @param array $propertyData
	 * @param array $valueRows
	 */
	public function __construct(array $propertyData, array $valueRows) {
		$this->propertyData = $propertyData;
		$this->valueRows = $valueRows;
	}


	/**
	 * Render the observation member's metadata.
	 *
	 * @param SimpleXMLElement $container
	 *
	 * @throws Exception
	 * @uses \Environet\Sys\Xml\Model\OutputXmlData::dateToISO()
	 */
	protected function renderMeta(SimpleXMLElement &$container) {
		$timePeriod = $container->addChild('om:phenomenonTime')->addChild('gml:TimePeriod');
		$timePeriod->addChild('gml:beginPosition', OutputXmlData::dateToISO($this->propertyData['phenomenon_time_begin']));
		$timePeriod->addChild('gml:endPosition', OutputXmlData::dateToISO($this->propertyData['phenomenon_time_end']));

		$container
			->addChild('om:resultTime')
			->addChild('gml:TimeInstant')
			->addChild('gml:timePosition', OutputXmlData::dateToISO($this->propertyData['time_series_result_time']));

		$processType = $container->addChild('om:procedure')->addChild('wml2:ObservationProcess')->addChild('wml2:processType');
		$processType->addAttribute('xlink:href', 'http://www.opengis.net/def/processType/WaterML/2.0/Sensor');
		$processType->addAttribute('xlink:title', 'Sensor');

		$symbol = $container->addChild('om:observedProperty');
		$symbol->addAttribute('xlink:href', $this->propertyData['property_symbol'] ?? '');
		$symbol->addAttribute('xlink:title', $this->propertyData['property_description'] ?? '');

		$monitoringPoint = $container->addChild('om:featureOfInterest')->addChild('wml2:MonitoringPoint');
		$monitoringPoint->addChild('gml:description', $this->propertyData['mpoint_location'] ?? '');
		$monitoringPoint
			->addChild('gml:identifier', $this->propertyData['eucd_wgst'] ?? $this->propertyData['eucd_pst'] ?? '')
			->addAttribute('codeSpace', 'https://www.icpdf.org/DanubeHIS/monitoringPoint');
		$monitoringPoint->addChild('gml:name', $this->propertyData['mpoint_name'] ?? '');
		$monitoringPoint->addChild('sa:sampledFeature')->addAttribute('xlink:title', $this->propertyData['mpoint_name'] ?? '');
		$monitoringPoint
			->addChild('sams:shape')
			->addChild('gml:Point')
			->addChild('gml:pos', "{$this->propertyData['lat']} {$this->propertyData['long']}")
			->addAttribute('srsName', 'urn:ogc:def:crs:EPSG::4326');
		$monitoringPoint
			->addChild('wml2:timeZone')
			->addChild('wml2:TimeZone')
			->addChild('wml2:zoneOffset', $this->propertyData['mpoint_utc_offset'] ?? 0);
	}


	/**
	 * Render the measurement members results.
	 *
	 * @param SimpleXMLElement $container
	 *
	 * @throws Exception
	 * @uses \Environet\Sys\Xml\Model\OutputXmlObservationMember::renderValues()
	 */
	protected function renderMeasurementResult(SimpleXMLElement &$container) {
		$timeSeries = $container->addChild('om:result')->addChild('wml2:MeasurementTimeseries');

		// Metadata
		$measurementMeta = $timeSeries->addChild('wml2:defaultPointMetadata')->addChild('wml2:DefaultTVPMeasurementMetadata');
		$measurementMeta->addChild('wml2:uom')->addAttribute('code', $this->propertyData['property_unit'] ?? '');

		$interpolationType = $measurementMeta->addChild('wml2:interpolationType');
		$interpolationType->addAttribute('xlink:href', 'http://www.opengis.net/def/waterml/2.0/interpolationType/Continuous');
		$interpolationType->addAttribute('xlink:title', 'Instantaneous');

		$this->renderValues($timeSeries);
	}


	/**
	 * Render Value points.
	 *
	 * @param SimpleXMLElement $timeSeries
	 *
	 * @throws Exception
	 * @uses \Environet\Sys\Xml\Model\OutputXmlData::dateToISO()
	 */
	protected function renderValues(SimpleXMLElement &$timeSeries) {
		// TVP Point
		foreach ($this->valueRows as $valueRow) {
			$tvp = $timeSeries->addChild('wml2:point')->addChild('wml2:MeasurementTVP');
			$tvp->addChild('wml2:time', OutputXmlData::dateToISO($valueRow['result_time']));
			$tvp->addChild('wml2:value', $valueRow['result_value'] ?? '');
		}
	}


	/**
	 * Render one observation member.
	 *
	 * @param SimpleXMLElement $parent
	 *
	 * @throws Exception
	 * @uses \Environet\Sys\Xml\Model\OutputXmlObservationMember::renderMeta()
	 * @uses \Environet\Sys\Xml\Model\OutputXmlObservationMember::renderMeasurementResult()
	 */
	public function render(SimpleXMLElement &$parent): void {
		$observation = $parent->addChild('wml2:observationMember')->addChild('om:OM_Observation');
		$this->renderMeta($observation);
		$this->renderMeasurementResult($observation);
	}


}
