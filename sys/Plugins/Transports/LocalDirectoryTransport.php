<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\TransportInterface;

/**
 * Class LocalDirectoryTransport
 *
 * @package Environet\Sys\Plugins\Transports
 * @author  Ádám Bálint <adam.balint@srg.hu>
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
		$console->writeLine("Configuring local directory transport");
		$path = $console->ask("Enter path to the directory where the data is:", 200);
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
        // Open a directory, and read its contents
        foreach(glob('/meteringdata/' . $this->path .'/*') as $path) {
            return [file_get_contents($path)];
        }

		return [''];
	}


    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return 'local directory transport';
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Local directory transport description';
    }
}
