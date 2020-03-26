<?php

namespace Environet\Sys\Plugins\Parsers;

use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\ParserInterface;

/**
 * Class JsonParser
 *
 * Parser layer for JSON files
 *
 * @package Environet\Sys\Plugins\Parsers
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class JsonParser implements ParserInterface, BuilderLayerInterface {


	/**
	 * @inheritDoc
	 */
	public function parse(string $data): array {
		return json_decode($data, true);
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


}
