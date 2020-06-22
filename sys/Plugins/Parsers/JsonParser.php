<?php

namespace Environet\Sys\Plugins\Parsers;

use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\ParserInterface;
use Environet\Sys\Plugins\Resource;
use Environet\Sys\Xml\CreateInputXml;
use Environet\Sys\Xml\Exceptions\CreateInputXmlException;
use Environet\Sys\Xml\Model\InputXmlData;
use Environet\Sys\Xml\Model\InputXmlPropertyData;

/**
 * Class JsonParser
 *
 * Parser layer for JSON files
 *
 * @package Environet\Sys\Plugins\Parsers
 * @author  SRG Group <dev@srg.hu>
 */
class JsonParser implements ParserInterface, BuilderLayerInterface {

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
	 * @throws CreateInputXmlException
	 * @see CreateInputXml
	 * @see InputXmlData
	 * @see InputXmlPropertyData
	 */
	public function parse(Resource $resource): array {

		$parsed = json_decode($resource->contents, true);
		$creator = new CreateInputXml();
		$property = new InputXmlPropertyData($this->propertySymbol, $parsed);

		return [$creator->generateXml(new InputXmlData($this->monitoringPointId, [$property]))];
	}


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console): ParserInterface {
		$console->writeLine('');
		$console->writeLine('Configuring json parser property');
		$monitoringPointId = $console->ask('Enter monitoring point id:');
		$propertySymbol = $console->ask('Enter property symbol:');

		$config = [
			'monitoringPointId' => $monitoringPointId,
			'propertySymbol'    => $propertySymbol
		];

		return new self($config);
	}


	/**
	 * JsonParser constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {
		$this->monitoringPointId = $config['monitoringPointId'];
		$this->propertySymbol = $config['propertySymbol'];
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
	 * @inheritDoc
	 */
	public static function getName(): string {
		return 'json parser';
	}


	/**
	 * @inheritDoc
	 */
	public static function getHelp(): string {
		return 'For parsing data in JSON format.';
	}


}
