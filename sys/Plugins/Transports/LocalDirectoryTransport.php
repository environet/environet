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


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console): TransportInterface {
		$console->writeLine('');
		$console->writeLine('Configuring local directory transport', Console::COLOR_YELLOW);

		$console->writeLine('Enter path to the directory where the data is. This should be a path relative to the LOCAL_DATA_DIR');
		$console->write('Leave empty if the data files are located immediately under that directory.');
		$path = $console->ask('', 200);
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
		$resources = [];
		foreach (glob("/meteringdata/{$this->path}/*") as $path) {
			$resource = new Resource();
			$pathParts = explode('/', $path);
			$resource->name = end($pathParts);
			$resource->contents = file_get_contents($path);
			$resources[] = $resource;
		}

		return $resources;
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
