<?php


namespace Environet\Sys\Xml\Model;

use DateTime;
use DateTimeZone;
use Environet\Sys\Xml\XmlRenderable;
use Exception;
use SimpleXMLElement;

/**
 * Class OutputXmlData
 *
 * Wrapper class for the complete output XML structure
 *
 * @package Environet\Sys\Xml\Model
 * @author  SRG Group <dev@srg.hu>
 */
class OutputXmlData implements XmlRenderable {

	protected array $queryMeta;

	/**
	 * @var XmlRenderable[]
	 */
	private $observationMembers;


	/**
	 * OutputXmlData constructor.
	 *
	 * @param array $observationMembers
	 * @param array $queryMeta
	 */
	public function __construct(array $observationMembers = [], array $queryMeta = []) {
		$this->observationMembers = $observationMembers;
		$this->queryMeta = $queryMeta;
	}


	/**
	 * Formats a valid date string to ISO 8601 date sting.
	 *
	 * @param $string
	 *
	 * @return string
	 * @throws Exception
	 */
	public static function dateToISO($string) {
		return (new DateTime($string, new DateTimeZone('UTC')))->format('c');
	}


	/**
	 * Add observation member to the output.
	 *
	 * @param OutputXmlObservationMember $member
	 */
	public function addObservationMember(OutputXmlObservationMember $member) {
		$this->observationMembers[] = $member;
	}


	/**
	 * Render items in the observation member collection.
	 *
	 * @param SimpleXMLElement $collection
	 *
	 * @uses \Environet\Sys\Xml\Model\OutputXmlObservationMember::render()
	 */
	protected function renderObservationMembers(SimpleXMLElement &$collection) {
		foreach ($this->observationMembers as $member) {
			$member->render($collection);
		}
	}


	/**
	 * Render the complete report
	 *
	 * @param SimpleXMLElement $xml
	 * @param array            $headers
	 *
	 * @throws Exception
	 * @uses \Environet\Sys\Xml\Model\OutputXmlData::renderObservationMembers()
	 */
	public function render(SimpleXMLElement &$xml, array &$headers): void {
		$meta = $xml->addChild('wml2:metadata', null, 'wml2');
		$docMeta = $meta->addChild('wml2:DocumentMetadata', null, 'wml2');
		$docMeta->addChild('wml2:generationDate', self::dateToISO('now'), 'wml2');

		$version = $docMeta->addChild('wml2:version', null, 'wml2');
		$version->addAttribute('xlink:href', 'http://www.opengis.net/waterml/2.0', 'xlink');
		$version->addAttribute('xlink:title', 'WaterML 2.0', 'xlink');

		$docMeta->addChild('wml2:generationSystem', 'HyMeDES EnviroNet', 'wml2');

		if (isset($this->queryMeta['intervalLimited']) && $this->queryMeta['intervalLimited'] === true) {
			$message = 'Start of time series was automatically limited to available data';
			$parameter = $xml->addChild('wml2:parameter', null, 'wml2');
			$parameterNV = $parameter->addChild('om:NamedValue', null, 'om');
			$parameterName = $parameterNV->addChild('om:name', null, 'om');
			$parameterName->addAttribute('xlink:title', 'message', 'xlink');
			$parameterNV->addChild('om:value', $message, 'om');
			$headers['X-Dareffort-Note'] = $message;
		}

		$this->renderObservationMembers($xml);
	}


}
