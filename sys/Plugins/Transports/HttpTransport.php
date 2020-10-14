<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\General\HttpClient\Exceptions\HttpClientException;
use Environet\Sys\General\HttpClient\HttpClient;
use Environet\Sys\General\HttpClient\Request;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\Resource;
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
	 * @var int 1 if the url is an index of files
	 */
	private $isIndex;

	/**
	 * @var string Regex pattern if the url is an index page
	 */
	private $indexRegexPattern;


	/**
	 * HttpTransport constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {
		$this->url = $config['url'];
		$this->isIndex = $config['isIndex'] ?? null;
		$this->indexRegexPattern = $config['indexRegexPattern'] ?? null;
	}


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console): HttpTransport {
		$console->writeLine('');
		$console->writeLine("Configuring http transport", Console::COLOR_YELLOW);
		$url = $console->ask("Enter the url of data to be imported e.g.: https://example.com/data.txt");

		$isIndex = $console->askWithDefault('Is the url is the list (index) of multiple files? [y/N]', 'n');
		$isIndex = trim(strtolower($isIndex)) === 'y';

		$indexRegexPattern = '';
		if ($isIndex) {
			//Get regex pattern finding files
			$indexRegexPattern = $console->ask("Please enter the regex patter for the relative path of files, with '/' as delimiter, and with named match with name 'relativePath'. Example: /<a href=\"(?P<relativePath>[^\"]+\.csv)\"/i");
		}

		$config = [
			'url' => $url,
			'isIndex' => $isIndex ? 1 : 0,
			'indexRegexPattern' => $indexRegexPattern
		];

		return new self($config);
	}


	/**
	 * @inheritDoc
	 */
	public function serializeConfiguration(): string {
		return 'url = "' . $this->url . '"' . "\n"
			. 'isIndex = ' . $this->isIndex . '' . "\n"
			. 'indexRegexPattern = "' . addcslashes($this->indexRegexPattern, '"') . '"' . "\n";
	}


	/**
	 * @inheritDoc
	 * @throws HttpClientException
	 */
	public function get(): array {
		$urls = [];
		if ($this->isIndex) {
			//Fetch files based on HTML source
			$indexPageContents = (new HttpClient())->sendRequest(new Request($this->url))->getBody();
			$hasMatches = preg_match_all($this->indexRegexPattern, $indexPageContents, $matches);
			if ($hasMatches && !empty($matches['relativePath'])) {
				$urls = array_map(function ($match) {
					return rtrim($this->url, '/') . '/' . ltrim($match, '/');
				}, $matches['relativePath']);
			}
		} else {
			//User url as file
			$urls = [$this->url];
		}

		$resources = [];
		foreach ($urls as $url) {
			$resource = new Resource();
			$resource->name = basename($url); //Filename
			$resource->contents = (new HttpClient())->sendRequest(new Request($url))->getBody();
			$resources[] = $resource;
		}

		return $resources;
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
