<?php


namespace Environet\Sys\Plugins\Parsers;

use DateTimeZone;
use Environet\Sys\Commands\Console;
use Environet\Sys\General\Model\Configuration\FormatsConfig;
use Environet\Sys\Plugins\ParserInterface;
use Exception;

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
	 * @var string|null
	 */
	protected $monitoringPointType;

	/**
	 * @var FormatsConfig|null Format specifications, where to find which information in xml file
	 */
	protected ?FormatsConfig $formatsConfig = null;

	/**
	 * @var string Filename of JSON file which contains formats for xml
	 */
	protected $formatsFilename;


	/**
	 * AbstractParser constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {
		$this->timeZone = $config['timeZone'] ?? 'UTC';
		$this->timeInFilenameFormat = $config['timeInFilenameFormat'] ?? null;
		$this->monitoringPointType = $config['monitoringPointType'] ?? null;
		$this->formatsFilename = $config['formatsFilename'] ?? null;
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
	 * @param Console $console
	 *
	 * @return mixed
	 */
	public static function createMonitoringPointTypeConfig(Console $console) {
		do {
			$console->writeLine('Do you want to restrict the transport to a monitoring point type? If yes, enter "hydro" of "meteo":', Console::COLOR_YELLOW);
			$type = $console->ask('Monitoring point type:');
		} while (!in_array($type, ['', 'hydro', 'meteo']));

		return $type;
	}


	/**
	 * Get timezone of plugin
	 * @return DateTimeZone
	 */
	protected function getTimeZone(): DateTimeZone {
		return new DateTimeZone($this->timeZone ?: 'UTC');
	}


	/**
	 * Get the configuration from formats.json, and build a FormatsConfig object from it
	 *
	 * @return FormatsConfig|null
	 * @throws Exception
	 */
	protected function getFormatsConfig(): ?FormatsConfig {
		if (is_null($this->formatsConfig)) {
			$formatsPathname = CONFIGURATION_PATH . '/' . $this->formatsFilename; //Path of file is in a fixed location
			$formats = null;
			if (!(file_exists($formatsPathname) && //File must be existing
				($formats = file_get_contents($formatsPathname)) && //File must be not-empty and readable
				($formats = json_decode($formats, true)) //Decode to json
			)) {
				throw new Exception("Syntax error in json string of formats configuration file '$formatsPathname', or file does not exist.");
			}
			$this->formatsConfig = new FormatsConfig($formats);
		}

		return $this->formatsConfig;
	}


}
