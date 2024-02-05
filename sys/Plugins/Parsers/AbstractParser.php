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
	 * @var string
	 */
	protected $timeInFilenameFormat;


	/**
	 * AbstractParser constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {
		$this->timeZone = $config['timeZone'] ?? 'UTC';
		$this->timeInFilenameFormat = $config['timeInFilenameFormat'] ?? null;
	}


	/**
	 * @param Console $console
	 *
	 * @return mixed
	 */
	public static function createTimeZoneConfig(Console $console) {
		$validList = array_merge(
			timezone_identifiers_list(),
			array_map('strtoupper', array_keys(timezone_abbreviations_list()))
		);
		do {
			$console->writeLine('Enter a source valid timezone string for conversion to UTC (e.g CET or Europe/Vienna)', Console::COLOR_YELLOW);
			$timezone = $console->ask('Timezone:');
		} while (!in_array($timezone, $validList));

		return $timezone;
	}


	/**
	 * Create settings for dates in file's name
	 *
	 * @param Console $console
	 *
	 * @return mixed
	 */
	public static function createTimeInFilenameConfig(Console $console): ?string {
		$console->writeLine('Is the time of measurement presented in filename?', Console::COLOR_YELLOW);
		$timeInFilename = $console->askWithDefault('[y/n]', 'n');
		$timeInFilename = trim(strtolower($timeInFilename)) === 'y';

		$timeInFilenameFormat = null;
		if ($timeInFilename) {
			$console->writeLine('In what format is the time represented in?', Console::COLOR_YELLOW);
			$timeInFilenameFormat = $console->ask('Time format (for example, the format \'Y-m-d H:i:s\' corresponds to dates such as: 2020-03-15 10:15:00, while \'Y.m.d. H:i\' would match 2020.03.15. 10:15):');
		}

		return $timeInFilenameFormat;
	}


	/**
	 * Get timezone of plugin
	 * @return DateTimeZone
	 */
	protected function getTimeZone(): DateTimeZone {
		return new DateTimeZone($this->timeZone ?: 'UTC');
	}


}
