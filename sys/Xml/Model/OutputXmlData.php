<?php


namespace Environet\Sys\Xml\Model;

use DateTime;
use Environet\Sys\Xml\XmlRenderable;
use Exception;
use SimpleXMLElement;

/**
 * Class OutputXmlData
 *
 * Wrapper class for the complete output XML structure
 *
 * @package   Environet\Sys\Xml\Model
 * @author    SRG Group <dev@srg.hu>
 * @copyright 2020 SRG Group Kft.
 */
class OutputXmlData implements XmlRenderable {

	/**
	 * @var XmlRenderable[]
	 */
	private $observationMembers;


	/**
	 * OutputXmlData constructor.
	 *
	 * @param array $observationMembers
	 */
	public function __construct(array $observationMembers = []) {
		$this->observationMembers = $observationMembers;
	}


	/**
	 * Formats a valid date string to ISO 8601 date sting
	 *
	 * @param $string
	 *
	 * @return string
	 * @throws Exception
	 */
	public static function dateToISO($string) {
		return (new DateTime($string))->format('c');
	}


	/**
	 * Add observation member to the output
	 *
	 * @param OutputXmlObservationMember $member
	 */
	public function addObservationMember(OutputXmlObservationMember $member) {
		$this->observationMembers[] = $member;
	}


	/**
	 * @param SimpleXMLElement $collection
	 */
	protected function renderObservationMembers(SimpleXMLElement &$collection) {
		foreach ($this->observationMembers as $member) {
			$member->render($collection);
		}
	}


	/**
	 * @inheritDoc
	 * Render the complete report
	 * @throws Exception
	 */
	public function render(SimpleXMLElement &$xml): void {
		$docMeta = $xml->addChild('wml2:metadata')->addChild('wml2:DocumentMetadata');
		$docMeta->addChild('wml2:generationDate', self::dateToISO('now'));

		$version = $docMeta->addChild('wml2:version');
		$version->addAttribute('xlink:href', 'http://www.opengis.net/waterml/2.0');
		$version->addAttribute('xlink:title', 'WaterML 2.0');

		$docMeta->addChild('wml2:generationSystem', 'HyMeDES EnviroNet');

		$this->renderObservationMembers($xml);
	}


}
