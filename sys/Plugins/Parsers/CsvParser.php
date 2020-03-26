<?php

namespace Environet\Sys\Plugins\Parsers;

use DateTime;
use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\ParserInterface;
use Environet\Sys\Xml\CreateInputXml;
use Environet\Sys\Xml\Exceptions\CreateInputXmlException;
use Environet\Sys\Xml\Model\InputXmlData;
use Environet\Sys\Xml\Model\InputXmlPropertyData;

/**
 * Class JsonParser
 *
 * Parser layer for CSV files
 *
 * @package Environet\Sys\Plugins\Parsers
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class CsvParser implements ParserInterface, BuilderLayerInterface {


    /**
     * @inheritDoc
     * @throws CreateInputXmlException
     */
	public function parse(string $data): array {

	    $result = [];

	    $lines = explode("\n", $data);


	    foreach ($lines as $line) {
	        $resultLine = $this->parseResultLine($line);
	        if(empty($resultLine)) continue;

	        if(!array_key_exists($resultLine['mPointId'], $result)) {
                $result[$resultLine['mPointId']] = [];
            }

	        if(!array_key_exists('h', $result[$resultLine['mPointId']])) {
                $result[$resultLine['mPointId']]['h'] = [];
            }

            $result[$resultLine['mPointId']]['h'] = array_merge($result[$resultLine['mPointId']]['h'], [[
                'time' => $resultLine['time'],
                'value' => $resultLine['h']
            ]]);
        }

	    $payloads = [];
        $creator = new CreateInputXml();
	    foreach($result as $mpointId => $properties) {
            $datas = [];
	        foreach ($properties as $propertySymbol => $results) {
                array_push($datas, new InputXmlPropertyData($propertySymbol, $results));

            }
            array_push($payloads, $creator->generateXml(new InputXmlData($mpointId, $datas)));
        }

		return $payloads;
	}

	private function parseResultLine($line): array {
        $values = explode( ';', $line);
        if(!$values[1]) {
            return [];
        }

        return [
            'mPointId' => $values[0],
            'time' => DateTime::createFromFormat('Y.m.d. H:i:s', $values[1])->format('Y-m-d\TH:i:sP'),
            'h' => $values[2]
        ];
    }


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console) {
		return new self([]);
	}


	/**
	 * @inheritDoc
	 */
	public function serializeConfiguration(): string {
		return '';
	}

    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return 'csv parser';
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'csv parser description';
    }

}
