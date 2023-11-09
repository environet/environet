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

	protected array $queryMeta;

	/**
	 * @var true
	 */
	private bool $intervalLimited = false;

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
	public function __construct(array $propertyData, array $valueRows, array $queryMeta) {
		$this->propertyData = $propertyData;
		$this->valueRows = $valueRows;
		$this->queryMeta = $queryMeta;
		foreach ($valueRows as $valueRow) {
			if (isset($valueRow['interval_limited']) && $valueRow['interval_limited'] === 1) {
				$this->intervalLimited = true;
				break;
			}
		}
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
		if ($this->intervalLimited) {
			$container->addChild('om:description', 'Start of time series was automatically limited to available data', 'om');
		}
		$timePeriod = $container->addChild('om:phenomenonTime', null, 'om')->addChild('gml:TimePeriod', null, 'gml');

		$startTimeRequest = $this->queryMeta['startTime'] ?? null;
		$endTimeRequest = $this->queryMeta['endTime'] ?? null;
		$startTimeSeries = $this->propertyData['phenomenon_time_begin'] ?? null;
		$endTimeSeries = $this->propertyData['phenomenon_time_end'] ?? null;

		$startTime = $startTimeRequest && strtotime($startTimeRequest) > strtotime($startTimeSeries) ? $startTimeRequest : $startTimeSeries;
		$endTime = $endTimeRequest && strtotime($endTimeRequest) < strtotime($endTimeSeries) ? $endTimeRequest : $endTimeSeries;

		$timePeriod->addChild('gml:beginPosition', $startTime ? OutputXmlData::dateToISO($startTime) : '', 'gml');
		$timePeriod->addChild('gml:endPosition', $endTime ? OutputXmlData::dateToISO($endTime) : '', 'gml');

		$resultTime = $endTime ? OutputXmlData::dateToISO($endTime) : '';
		$container
			->addChild('om:resultTime', null, 'om')
			->addChild('gml:TimeInstant', null, 'gml')
			->addChild('gml:timePosition', $resultTime, 'gml');

		$processType = $container->addChild('om:procedure', null, 'om')
			->addChild('wml2:ObservationProcess', null, 'wml2')
			->addChild('wml2:processType', null, 'wml2');
		$processType->addAttribute('xlink:href', 'http://www.opengis.net/def/processType/WaterML/2.0/Sensor', 'xlink');
		$processType->addAttribute('xlink:title', 'Sensor', 'xlink');

		$symbol = $container->addChild('om:observedProperty', null, 'om');
		$symbol->addAttribute('xlink:href', $this->propertyData['property_symbol'] ?? '', 'xlink');
		$symbol->addAttribute('xlink:title', $this->propertyData['property_description'] ?? '', 'xlink');

		$monitoringPoint = $container->addChild('om:featureOfInterest', null, 'om')
			->addChild('wml2:MonitoringPoint', null, 'wml2');
		$monitoringPoint->addChild('gml:description', $this->propertyData['mpoint_location'] ?? '', 'gml');
		$monitoringPoint
			->addChild('gml:identifier', $this->propertyData['eucd_wgst'] ?? $this->propertyData['eucd_pst'] ?? '', 'gml')
			->addAttribute('codeSpace', 'https://www.icpdr.org/DanubeHIS/monitoringPoint');
		$monitoringPoint->addChild('gml:name', $this->propertyData['mpoint_name'] ?? '', 'gml');
		$monitoringPoint->addChild('sa:sampledFeature', null, 'sa')
			->addAttribute('xlink:title', $this->propertyData['mpoint_name'] ?? '', 'xlink');
		$monitoringPoint
			->addChild('sams:shape', null, 'sams')
			->addChild('gml:Point', null, 'gml')
			->addChild('gml:pos', "{$this->propertyData['lat']} {$this->propertyData['long']}", 'gml')
			->addAttribute('srsName', 'urn:ogc:def:crs:EPSG::4326');
		$monitoringPoint
			->addChild('wml2:timeZone', null, 'wml2')
			->addChild('wml2:TimeZone', null, 'wml2')
			->addChild('wml2:zoneOffset', $this->propertyData['mpoint_utc_offset'] ?? 0, 'wml2');
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
		$timeSeries = $container->addChild('om:result', null, 'om')
			->addChild('wml2:MeasurementTimeseries', null, 'wml2');

		// Metadata
		$measurementMeta = $timeSeries->addChild('wml2:defaultPointMetadata', null, 'wml2')
			->addChild('wml2:DefaultTVPMeasurementMetadata', null, 'wml2');
		$measurementMeta->addChild('wml2:uom', null, 'wml2')->addAttribute('code', $this->propertyData['property_unit'] ?? '');

		$interpolationType = $measurementMeta->addChild('wml2:interpolationType', null, 'wml2');
		$interpolationType->addAttribute('xlink:href', 'http://www.opengis.net/def/waterml/2.0/interpolationType/Continuous', 'xlink');
		$interpolationType->addAttribute('xlink:title', 'Instantaneous', 'xlink');

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
			$tvp = $timeSeries->addChild('wml2:point', null, 'wml2')->addChild('wml2:MeasurementTVP', null, 'wml2');
			$resultTime = !empty($valueRow['result_time']) ? OutputXmlData::dateToISO($valueRow['result_time']) : '';
			$tvp->addChild('wml2:time', $resultTime, 'wml2');
			$tvp->addChild('wml2:value', $valueRow['result_value'] ?? '', 'wml2');
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
		$observation = $parent->addChild('wml2:observationMember', null, 'wml2')->addChild('om:OM_Observation', null, 'om');
		$this->renderMeta($observation);
		$this->renderMeasurementResult($observation);
	}


}
