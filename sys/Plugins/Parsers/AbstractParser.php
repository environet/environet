<?php


namespace Environet\Sys\Plugins\Parsers;

use DateTimeZone;
use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\ParserInterface;

/**
 * Class AbstractParser
 *
 * @package Environet\Sys\Plugins\Parsers
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
abstract class AbstractParser implements ParserInterface {


	/**
	 * @var string
	 */
	protected $timeZone;


	/**
	 * AbstractParser constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {
		$this->timeZone = $config['timeZone'] ?? 'UTC';
	}


	/**
	 * @param Console $console
	 *
	 * @return mixed
	 */
	public static function createTimeZoneConfig(Console $console) {
		$validList = array_merge(timezone_identifiers_list(), timezone_abbreviations_list());
		do {
			$console->writeLine('Enter a source valid timezone string for conversion to UTC', Console::COLOR_YELLOW);
			$timezone = $console->ask('Timezone:');
		} while (!in_array($timezone, $validList));

		return $timezone;
	}


	/**
	 * Get timezone of plugin
	 * @return DateTimeZone
	 */
	protected function getTimeZone(): DateTimeZone {
		return new DateTimeZone($this->timeZone ?: 'UTC');
	}

}