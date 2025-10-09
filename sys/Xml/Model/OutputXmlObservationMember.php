<?php

namespace Environet\Sys\Xml\Model;

use DOMDocument;
use Exception;

/**
 * Wrapper class for handling WML observationMember data structure
 */
class OutputXmlObservationMember {


	protected array $queryMeta;

	/** @var array Property data for the current observation member. It is set when rendering value rows. */
	protected array $propertyData = [];

	/** @var true */
	private bool $intervalLimited = false;


	public function __construct(array $queryMeta) {
		$this->queryMeta = $queryMeta;
	}


	/**
	 * Render the observation member's metadata.
	 *
	 * @throws Exception
	 */
	public function renderObservationMember(): string {
		if (empty($this->propertyData)) {
			throw new Exception('Property data not set for observation member rendering. Render value rows first.');
		}

		$doc = new DOMDocument('1.0', 'UTF-8');
		$observation = $doc->appendChild($doc->createElement('wml2:observationMember'));
		$observation = $observation->appendChild($doc->createElement('om:OM_Observation'));
		if ($this->intervalLimited) {
			$observation->appendChild($doc->createElement('gml:description', 'Start of time series was automatically limited to available data'));
		}
		$observation
			->appendChild($doc->createElement('om:phenomenonTime'))
			->appendChild($timePeriod = $doc->createElement('gml:TimePeriod'));

		$startTimeRequest = $this->queryMeta['startTime'] ?? null;
		$endTimeRequest = $this->queryMeta['endTime'] ?? null;
		$startTimeSeries = $this->propertyData['phenomenon_time_begin'] ?? null;
		$endTimeSeries = $this->propertyData['phenomenon_time_end'] ?? null;

		$startTime = $startTimeRequest && strtotime($startTimeRequest) > strtotime($startTimeSeries) ? $startTimeRequest : $startTimeSeries;
		$endTime = $endTimeRequest && strtotime($endTimeRequest) < strtotime($endTimeSeries) ? $endTimeRequest : $endTimeSeries;

		$timePeriod->appendChild($doc->createElement('gml:beginPosition', $startTime ? dateToISO($startTime) : ''));
		$timePeriod->appendChild($doc->createElement('gml:endPosition', $endTime ? dateToISO($endTime) : ''));

		$resultTime = $endTime ? dateToISO($endTime) : '';
		$observation
			->appendChild($doc->createElement('om:resultTime'))
			->appendChild($doc->createElement('gml:TimeInstant'))
			->appendChild($doc->createElement('gml:timePosition', $resultTime));

		$observation
			->appendChild($doc->createElement('om:procedure'))
			->appendChild($doc->createElement('wml2:ObservationProcess'))
			->appendChild($processType = $doc->createElement('wml2:processType'));
		$processType->setAttribute('xlink:href', 'http://www.opengis.net/def/processType/WaterML/2.0/Sensor');
		$processType->setAttribute('xlink:title', 'Sensor');

		$observation->appendChild($symbol = $doc->createElement('om:observedProperty'));
		$symbol->setAttribute('xlink:href', $this->propertyData['property_symbol'] ?? '');
		$symbol->setAttribute('xlink:title', $this->propertyData['property_description'] ?? '');

		$observation->appendChild($doc->createElement('om:featureOfInterest'))
			->appendChild($monitoringPoint = $doc->createElement('wml2:MonitoringPoint'));
		$monitoringPoint->appendChild($doc->createElement('gml:description', $this->propertyData['mpoint_location'] ?? ''));

		$identifier = $doc->createElement('gml:identifier', $this->propertyData['eucd_wgst'] ?? $this->propertyData['eucd_pst'] ?? '');
		$identifier->setAttribute('codeSpace', 'https://www.icpdr.org/DanubeHIS/monitoringPoint');
		$monitoringPoint->appendChild($identifier);

		$monitoringPoint->appendChild($doc->createElement('gml:name', $this->propertyData['mpoint_name'] ?? ''));

		$sampledFeature = $doc->createElement('sa:sampledFeature');
		$sampledFeature->setAttribute('xlink:title', $this->propertyData['mpoint_name'] ?? '');
		$monitoringPoint->appendChild($sampledFeature);

		$pos = $doc->createElement('gml:pos', "{$this->propertyData['lat']} {$this->propertyData['long']}");
		$pos->setAttribute('srsName', 'urn:ogc:def:crs:EPSG::4326');
		$monitoringPoint
			->appendChild($doc->createElement('sams:shape'))
			->appendChild($doc->createElement('gml:Point'))
			->appendChild($pos);

		$monitoringPoint
			->appendChild($doc->createElement('wml2:timeZone'))
			->appendChild($doc->createElement('wml2:TimeZone'))
			->appendChild($doc->createElement('wml2:zoneOffset', $this->propertyData['mpoint_utc_offset'] ?? 0));

		$observation->appendChild($doc->createElement('tmp:result'));

		return $doc->saveXML();
	}


	/**
	 * Render the measurement members results.
	 *
	 * @throws Exception
	 */
	public function renderMeasurementResult(): string {
		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->appendChild($doc->createElement('om:result'))
			->appendChild($timeSeries = $doc->createElement('wml2:MeasurementTimeseries'));

		// Metadata
		$timeSeries->appendChild($doc->createElement('wml2:defaultPointMetadata'))
			->appendChild($measurementMeta = $doc->createElement('wml2:DefaultTVPMeasurementMetadata'));

		$uom = $doc->createElement('wml2:uom');
		$uom->setAttribute('code', $this->propertyData['property_unit'] ?? '');
		$measurementMeta->appendChild($uom);

		$interpolationType = $doc->createElement('wml2:interpolationType');
		$interpolationType->setAttribute('xlink:href', 'http://www.opengis.net/def/waterml/2.0/interpolationType/Continuous');
		$interpolationType->setAttribute('xlink:title', 'Instantaneous');
		$measurementMeta->appendChild($interpolationType);

		$timeSeries->appendChild($doc->createElement('tmp:values'));

		return $doc->saveXML();
	}


	/**
	 * Render Value points.
	 *
	 * @throws Exception
	 */
	public function renderValue(array $valueRow): string {
		$this->propertyData = $valueRow;
		if (isset($valueRow['interval_limited']) && $valueRow['interval_limited'] === 1) {
			$this->intervalLimited = true;
		}
		$resultTime = !empty($valueRow['result_time']) ? dateToISO($valueRow['result_time']) : '';

		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->appendChild($doc->createElement('wml2:point'))
			->appendChild($tvp = $doc->createElement('wml2:MeasurementTVP'));
		$tvp->appendChild($doc->createElement('wml2:time', $resultTime));
		$tvp->appendChild($doc->createElement('wml2:value', $valueRow['result_value'] ?? ''));

		return $doc->saveXML();
	}


}
