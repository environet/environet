<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\TransportInterface;

/**
 * Class LocalFileTransport
 *
 * Transport layer for importing data from a local file.
 *
 * @package Environet\Sys\Plugins\Transports
 * @author  SRG Group <dev@srg.hu>
 */
class LocalFileTransport implements TransportInterface, BuilderLayerInterface {

	/**
	 * @var string
	 */
	private $path;


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console): TransportInterface {
		$console->writeLine('');
		$console->writeLine('Configuring local file transport', '32');
		$path = $console->ask('Enter path to the file to be imported:', 200);
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
	 * LocalFileTransport constructor.
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
		$resource = new Resource();
		$resource->name = $this->path;
		$resource->contents = file_get_contents('/meteringdata/' . $this->path);

		return [$resource];
	}


	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return 'local file transport';
	}


}
