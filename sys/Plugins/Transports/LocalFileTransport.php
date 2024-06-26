<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\PluginBuilder;
use Environet\Sys\Plugins\Resource;
use Environet\Sys\Plugins\TransportInterface;

/**
 * Class LocalFileTransport
 *
 * Transport layer for importing data from a local file.
 *
 * @package Environet\Sys\Plugins\Transports
 * @author  SRG Group <dev@srg.hu>
 */
class LocalFileTransport extends AbstractTransport {

	/**
	 * @var string
	 */
	private $path;


	private static function getDataDirDisplay(): string {
		if (substr(getenv('LOCAL_DATA_DIR'), 0, 1) == '/') {
			return getenv('LOCAL_DATA_DIR');
		} elseif (substr(getenv('LOCAL_DATA_DIR'), 0, 3) == '../') {
			return '[Environet docker directory]/' . preg_replace('/^\.\.\//', '', getenv('LOCAL_DATA_DIR'));
		} else {
			return '[Environet docker directory]/docker/' . getenv('LOCAL_DATA_DIR');
		}
	}


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console, PluginBuilder $builder): TransportInterface {
		$console->writeLine('');
		$console->writeLine('Configuring local file transport', Console::COLOR_YELLOW);

		$monitoringPointType = self::createMonitoringPointTypeConfig($console);

		$console->writeLine('Enter path to the file to be imported. This should be relative to ' . self::getDataDirDisplay());

		$path = $console->ask('');

		while (empty($path)) {
			$console->writeLine('Not a valid file path', Console::COLOR_RED);
			$console->writeLine('Enter path to the file to be imported. This should be relative to ' . self::getDataDirDisplay());
			$path = $console->ask('');
		}

		if (!file_exists('/meteringdata/' . $path)) {
			$console->writeLine('The file ' . self::getDataDirDisplay() . '/' . $path . 'does not exist. You can create this file later and continue with the plugin configuration.', Console::COLOR_RED);
			if (!$console->askYesNo("Do you want to continue?", false)) {
				exit;
			}
		}

		$config = [
			'path' => $path,
			'monitoringPointType' => $monitoringPointType ?: null,
		];

		return new self($config);
	}


	/**
	 * @inheritDoc
	 */
	public function serializeConfiguration(): string {
		return 'path = "' . $this->path . '"' . "\n"
			. 'monitoringPointType = "' . $this->monitoringPointType . '"' . "\n";
	}


	/**
	 * LocalFileTransport constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {
		$this->path = $config['path'];
		parent::__construct($config);
	}


	/**
	 * @inheritDoc
	 * @see Resource
	 */
	public function get(Console $console, string $configFile): array {
		$resource = new Resource();
		$resource->setName($this->path);

		if (!file_exists('/meteringdata/' . $this->path)) {
			Console::getInstance()->writeLine('The file at \'' . self::getDataDirDisplay() . '/' . $this->path . '\' does not exist', Console::COLOR_RED);

			return [];
		}

		$resource->setContents(file_get_contents('/meteringdata/' . $this->path));

		return [$resource];
	}


	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return 'local file transport';
	}


	/**
	 * @inheritDoc
	 */
	public static function getHelp(): string {
		return 'Reads data from a specific file. Use if measurements are stored in one file, the contents of which are updated over time with new measurements.';
	}


}
