<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\General\HttpClient\Exceptions\HttpClientException;
use Environet\Sys\General\HttpClient\HttpClient;
use Environet\Sys\General\HttpClient\Request;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\TransportInterface;

/**
 * Class HttpTransport
 *
 * Transport layer for http connections.
 *
 * @package Environet\Sys\Plugins\Transports
 * @author  SRG Group <dev@srg.hu>
 */
class HttpTransport implements TransportInterface, BuilderLayerInterface {

	/**
	 * @var string URL of the data source
	 */
	private $url;


	/**
	 * HttpTransport constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {
		$this->url = $config['url'];
	}


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console): HttpTransport {
		$console->writeLine('');
		$console->writeLine("Configuring http transport", Console::COLOR_YELLOW);
		$url = $console->ask("Enter the url of data to be imported e.g.: https://example.com/data.txt");
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
	 * @inheritDoc
	 * @throws HttpClientException
	 */
	public function get(): array {
		return [(new HttpClient())->sendRequest(new Request($this->url))->getBody()];
	}


	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return 'http transport';
	}


	/**
	 * @inheritDoc
	 */
	public static function getHelp(): string {
		return 'For acquiring measurement data via an http request.';
	}


}
