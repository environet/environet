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

	protected array $configArray;


	/**
	 * HttpTransport constructor.
	 *
	 * @param array $config
	 * @param array $pluginConfig
	 */
	public function __construct(array $config, array $pluginConfig = []) {
		$this->configArray = $config;
		$this->monitoringPointType = $config['monitoringPointType'] ?? null;
	}


	/**
	 * @return array
	 */
	public function getConfigArray(): array {
		return $this->configArray;
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
	 * Get files under processed directory
	 *
	 * @param string $configuration
	 *
	 * @return array
	 */
	protected function getProcessedFiles(string $configuration): array {
		$localFileDir = $this->getLocalFileDir($configuration);
		$processedFileDir = rtrim($localFileDir, '/') . '/processed/';
		$files = scandir($processedFileDir);

		return array_filter($files, function ($file) {
			return !in_array($file, ['.', '..']);
		});
	}


	/**
	 * Get directory of local copy files
	 *
	 * @param string $configuration
	 *
	 * @return string
	 */
	protected function getLocalFileDir(string $configuration): string {
		return SRC_PATH . '/data/plugin_input_files/' . $configuration . '/';
	}


}
