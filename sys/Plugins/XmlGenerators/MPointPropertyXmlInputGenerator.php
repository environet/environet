<?php

namespace Environet\Sys\Plugins\XmlGenerators;

use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\XmlGeneratorInterface;
use Environet\Sys\Xml\CreateInputXml;
use Environet\Sys\Xml\Exceptions\CreateInputXmlException;
use Environet\Sys\Xml\Model\InputXmlData;
use Environet\Sys\Xml\Model\InputXmlPropertyData;
use SimpleXMLElement;

/**
 * Class MPointPropertyXmlInputGenerator
 *
 * @package Environet\Sys\Plugins\XmlGenerators
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class MPointPropertyXmlInputGenerator implements XmlGeneratorInterface, BuilderLayerInterface {

	/**
	 * @var string
	 */
	private $monitoringPointId;

	/**
	 * @var string
	 */
	private $propertySymbol;


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console): XmlGeneratorInterface {
		$console->writeLine("Configuring local file transport");
		$monitoringPointId = $console->ask("Enter monitoring point id:");
		$propertySymbol = $console->ask("Enter property symbol:");

		$config = [
			'monitoringPointId' => $monitoringPointId,
			'propertySymbol'    => $propertySymbol
		];

		return new self($config);
	}


	/**
	 * @inheritDoc
	 */
	public function serializeConfiguration(): string {
		$result = '';
		$result .= "monitoringPointId = $this->monitoringPointId\n";
		$result .= "propertySymbol = $this->propertySymbol\n";

		return $result;
	}


	/**
	 * MPointPropertyXmlInputGenerator constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {
		$this->monitoringPointId = $config['monitoringPointId'];
		$this->propertySymbol = $config['propertySymbol'];
	}


	/**
	 * @param array $data
	 *
	 * @return SimpleXMLElement
	 * @throws CreateInputXmlException
	 */
	public function generateXml(array $data): SimpleXMLElement {
		$creator = new CreateInputXml();
		$property = new InputXmlPropertyData($this->propertySymbol, $data);

		return $creator->generateXml(new InputXmlData($this->monitoringPointId, [$property]));
	}


}
