<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\TransportInterface;

/**
 * Class HttpTransport
 *
 * Transport layer through http connection
 *
 * @package Environet\Sys\Plugins\Transports
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class HttpTransport implements TransportInterface, BuilderLayerInterface {

	/**
	 * @var string URL of data source
	 */
	private $url;


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console): HttpTransport {
		$console->writeLine("Configuring http transport");
		$url = $console->ask("Enter url of data to be imported:", 200);
		$config = [
			'url' => $url,
		];

		return new self($config);
	}


	/**
	 * @inheritDoc
	 */
	public function serializeConfiguration(): string {
		return 'url = ' . $this->url . "\n";
	}


	/**
	 * HttpTransport constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {
		$this->url = $config['url'];
	}


	/**
	 * @return string
	 */
	public function get(): string {
		//@TODO make it with own HTTP Client
		$cURLConnection = curl_init();
		curl_setopt($cURLConnection, CURLOPT_URL, $this->url);
		curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);

		return curl_exec($cURLConnection);
	}


}
