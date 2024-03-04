<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\TransportInterface;

/**
 * Class AbstractTransport
 *
 * Transport layer defaults
 *
 * @package Environet\Sys\Plugins\Transports
 * @author  SRG Group <dev@srg.hu>
 */
abstract class AbstractTransport implements TransportInterface, BuilderLayerInterface {

	/**
	 * @var string|null
	 */
	protected $monitoringPointType;


	/**
	 * HttpTransport constructor.
	 *
	 * @param array $config
	 * @param array $pluginConfig
	 */
	public function __construct(array $config, array $pluginConfig = []) {
		$this->monitoringPointType = $config['monitoringPointType'] ?? null;
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


}
