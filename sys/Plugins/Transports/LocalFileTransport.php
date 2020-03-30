<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\TransportInterface;

/**
 * Class LocalFileTransport
 *
 * @package Environet\Sys\Plugins\Transports
 * @author  Ádám Bálint <adam.balint@srg.hu>
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
		$console->writeLine("Configuring local file transport", '32');
		$path = $console->ask("Enter path to the file to be imported:", 200);
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
