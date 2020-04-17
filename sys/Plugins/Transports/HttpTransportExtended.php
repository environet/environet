<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\General\HttpClient\Exceptions\HttpClientException;
use Environet\Sys\General\HttpClient\HttpClient;
use Environet\Sys\General\HttpClient\Request;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\TransportInterface;

/**
 * Class HttpTransportExtended
 *
 * Transport layer for http connections. Generalizes URL to also contain variables in square brackets, 
 * and files in a zip file separated by a pipe symbol. It also supports wildcards * and ? for file names in
 * zip files.
 *
 * @package Environet\Sys\Plugins\Transports
 * @author  SRG Group <dev@srg.hu>, <meyer@stasa.de>
 */
class HttpTransportExtended implements TransportInterface, BuilderLayerInterface {

	/**
	 * @var string URL of the data source
	 */
	private $url;


	/**
	 * HttpTransportExtended constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {
		$this->url = $config['url'];
	}


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console): HttpTransportExtended {
		$console->writeLine('');
		$console->writeLine("Configuring extended http transport", Console::COLOR_YELLOW);
		$url = $console->ask("Enter the url of data to be imported e.g.: https://example.com/data_[VARIABLE1].zip|file_in_zip_[VARIABLE2].txt", 200);
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
	public function get(array $variables): array {
		$variables = isset($variables) ? $variables : [];

		// do variable substitution
		$url = $this->url;
		foreach($variables as $key => $value) {
			$url = str_replace('[' . $key . ']', $value, $url);
		}

		// remove part after pipe symbol
		$parts = explode('|', $url);
		$url = $parts[0];

		if (sizeof($parts) > 1) {
			// contains pipe symbol, decode zip
			$ext = pathinfo($url, PATHINFO_EXTENSION);
			$temp = tempnam(sys_get_temp_dir(), $ext);
			copy($url, $temp);

			$zip = new ZipArchive;
			$zip->open($temp);
			$found = false;
			for ($i = 0; $i < $zip->numFiles; ++$i) {
				$name = $zip->getNameIndex($i);
				if (fnmatch($parts[1], $name)) {
					$body = $zip->getFromName($name);
					$found = true;
					break;
				}
			}
			$zip->close();
			if (!$found) $body = '';
			unlink($temp);
		} else {
			$body = (new HttpClient())->sendRequest(new Request($url))->getBody();
		}
		
		return [ $body ];
	}


	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return 'http transport with extended url syntax';
	}


	/**
	 * @inheritDoc
	 */
	public static function getHelp(): string {
		return 'For acquiring measurement data via an http request using url syntax extension.';
	}


}
