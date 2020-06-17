<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\TransportInterface;

/**
 * Class LocalDirectoryTransport
 *
 * Transport layer for importing data from a local directory.
 *
 * @package Environet\Sys\Plugins\Transports
 * @author  SRG Group <dev@srg.hu>
 */
class LocalDirectoryTransport implements TransportInterface, BuilderLayerInterface {

	/**
	 * @var string
	 */
	private $path;

	private static function getDataDirDisplay(): string {
		return (substr(getenv('LOCAL_DATA_DIR'), 0, 1) == '/' ? '' : '[Environet docker directory]/') . getenv('LOCAL_DATA_DIR');
	}
	
	/**
	 * @inheritDoc
	 */
	public static function create(Console $console): TransportInterface {
		$console->writeLine('');
		$console->writeLine('Configuring local directory transport', Console::COLOR_YELLOW);
		
		$console->writeLine('Enter path to the directory where the data files are. This should be a path relative to ' . self::getDataDirDisplay());
		$console->write('Leave empty if the data files are located immediately under that directory.');
		$path = $console->ask('');
		
		if(!file_exists('/meteringdata/' . $path)) {
			$console->writeLine('The folder ' . self::getDataDirDisplay() . '/' . $path . 'does not exist. You can create this folder later and continue with the plugin configuration.', Console::COLOR_RED);
			if (!$console->askYesNo("Do you want to continue?", false)) {
				exit;
			}
		}
		
		
		$config = [
			'path' => $path,
		];

		return new self($config);
	}


	/**
	 * @inheritDoc
	 */
	public function serializeConfiguration(): string {
		return 'path = ' . $this->path . "\n";
	}


	/**
	 * LocalDirectoryTransport constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {
		$this->path = $config['path'];
	}


	/**
	 * @inheritDoc
	 * @see Resource
	 */
	public function get(): array {
		$console = Console::getInstance();
		$localFileDir = SRC_PATH . '/data/plugin_input_files/';
		$localCopyPath = $localFileDir . $this->path;

		if (!file_exists($localCopyPath)) {
			mkdir($localCopyPath, 0755, true);
		}

		$dataFolderFiles = array_filter(glob("/meteringdata/{$this->path}/*"), 'is_file');
		
		$console->writeLine('There are ' . count($dataFolderFiles) . ' files in the data folder (' . self::getDataDirDisplay() . ')');
		
		$newOrChangedFiles = [];
		foreach ($dataFolderFiles as $filePath) {
			$filePathArray = explode('/', $filePath);
			$localCopyPath = $localFileDir . $this->path . '/' . end($filePathArray);

			// If there is no local copy of the file, or it was changed later than the local copy
			if (!file_exists($localCopyPath) || filemtime($localCopyPath) < filemtime($filePath)) {
				file_put_contents($localCopyPath, file_get_contents($filePath));
				$resource = new Resource();
				$resource->name = end($filePathArray);
				$resource->contents = file_get_contents($filePath);
				$newOrChangedFiles[] = $resource;
			}
		}
		
		$console->writeLine(count($newOrChangedFiles) . ' files have changed since the last run');

		return $newOrChangedFiles;
	}


	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return 'local directory transport';
	}


	/**
	 * @inheritDoc
	 */
	public static function getHelp(): string {
		return 'Reads files from a directory. Useful for when measurements are stored in multiple files inside a directory';
	}


}
