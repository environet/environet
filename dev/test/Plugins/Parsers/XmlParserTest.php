<?php

namespace Plugins\Parsers;

use Environet\Sys\Plugins\Parsers\XmlParser;
use Environet\Sys\Plugins\Parsers\XmlParserOld;
use Environet\Sys\Plugins\Resource;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * Class XmlParserTest
 *
 * @package Plugins\Parsers
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class XmlParserTest extends TestCase {

	/**
	 * @var array|false
	 */
	protected $configuration;

	/**
	 * @var Resource
	 */
	protected $resource;


	/**
	 *
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->configuration = parse_ini_file(CONFIGURATION_PATH.'/http_xml.conf');

		$this->resource = new Resource();
		$this->resource->setName('test');
		$this->resource->setContents(file_get_contents(TEST_RESOURCES_PATH.'/xmls/arso_hydro.xml'));
		//$this->resource->meta = include TEST_RESOURCES_PATH.'/xmls/arso_hydro_meta.php';
 	}


	/**
	 * @throws \Exception
	 */
	public function testParse() {
		$parser = new XmlParser($this->configuration);

		$xmls = $parser->parse($this->resource);
		$this->assertNotEmpty($xmls);

		$firstXml = $xmls[0];

		$this->assertEquals(1060, (string) $firstXml->xpath('//environet:UploadData/environet:MonitoringPointId')[0]);
		$this->assertEquals('h', (string) $firstXml->xpath('//environet:UploadData/environet:Property/environet:PropertyId')[0]);
		$this->assertEquals('2020-10-27T09:00:00+00:00', (string) $firstXml->xpath('//environet:UploadData/environet:Property/environet:TimeSeries/environet:Point/environet:PointTime')[0]);
		$this->assertEquals('122', (string) $firstXml->xpath('//environet:UploadData/environet:Property/environet:TimeSeries/environet:Point/environet:PointValue')[0]);
	}


}
