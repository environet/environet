<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\Resource;
use Environet\Sys\Plugins\TransportInterface;

/**
 * Class FtpTransport
 *
 * Transport layer for importing data from a FTP directory.
 *
 * @package Environet\Sys\Plugins\Transports
 * @author  SRG Group <dev@srg.hu>
 */
class FtpTransport implements TransportInterface, BuilderLayerInterface {

	/**
	 * @var bool
	 */
	private $secure;

	/**
	 * @var string
	 */
	private $host;

	/**
	 * @var string
	 */
	private $username;

	/**
	 * @var string
	 */
	private $password;

	/**
	 * @var string
	 */
	private $path;

	/**
	 * @var string
	 */
	private $filenamePattern;

	/**
	 * @var bool
	 */
	private $newestFileOnly;

	/**
	 * @inheritDoc
	 */
	public static function create(Console $console): TransportInterface {
		$console->writeLine('');
		$console->writeLine('Configuring FTP transport', Console::COLOR_YELLOW);

		$console->writeLine('FTP host:');
		$host = $console->ask('');

		$console->writeLine('Secure connection:');
		$console->write('Enter 0 for an FTP connection without SSL');
		$secure = $console->askWithDefault('Secure FTP connection', true);

		$console->writeLine('FTP username:');
		$username = $console->ask('');

		$console->writeLine('FTP password:');
		$password = $console->ask('');

		$console->writeLine('Enter path to the directory where the data files are located on the FTP server.');
		$console->write('Leave empty if the data files are in the FTP root directory.');
		$path = $console->ask('');

		$console->writeLine('Enter filename pattern of files to consider. Use an asterisk (*) for variable parts of the filename.');
		$console->write('Filename pattern, leave empty for all files:');
		$filenamePattern = $console->ask('');

		$console->writeLine('Newest file only:');
		$console->write('Enter 1 if only the newest of all matching files should be used:');
		$newestFileOnly = $console->askWithDefault('', false);

		$config = [
			'host' => $host,
			'secure' => $secure,
			'username' => $username,
			'password' => $password,
			'path' => $path,
			'filenamePattern' => $filenamePattern,
			'newestFileOnly' => $newestFileOnly,
		];

		return new self($config);
	}


	/**
	 * @inheritDoc
	 */
	public function serializeConfiguration(): string {
		return 'host = "' . $this->host . '"' . "\n"
			. 'secure = "' . $this->secure .'"' . "\n"
			. 'username = "' . $this->username . '"' . "\n"
			. 'password = "' . $this->password . '"' . "\n"
			. 'path = "' . $this->path . '"' . "\n"
			. 'filenamePattern = "' . $this->filenamePattern . '"' . "\n"
			. 'newestFileOnly = "' . $this->newestFileOnly . '"' . "\n";
	}


	/**
	 * FtpDirectoryTransport constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {
		$this->host = $config['host'];
		$this->secure = $config['secure'];
		$this->username = $config['username'];
		$this->password = $config['password'];
		$this->path = $config['path'];
		$this->filenamePattern = $config['filenamePattern'];
		$this->newestFileOnly = $config['newestFileOnly'];
	}


	public function newestFile(array $files) {
		$out = [];
		$newest = 0;
		foreach ($files as $entry) {
			if ($this->filenamePattern !== '' && !fnmatch($this->filenamePattern, $entry['name'])) continue;
			if (count($out) > 0 && $entry['modify'] < $newest) continue;
			if (count($out) == 0) array_push($out, $entry);
			else $out[0] = $entry;
			$newest = $entry['modify'];
		}
		return $out;
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

		$results = [];
		
		$conn = $this->secure ? ftp_ssl_connect($this->host) : ftp_connect($this->host);

		$login_result = ftp_login($conn, $this->username, $this->password);
		ftp_pasv($conn, true);
		if ($login_result) {
			$console->writeLine('Logged in to ftp server', Console::COLOR_YELLOW);
		}

		//ftp_chdir($conn, $this->path);

		$path = $this->path;
		if ($path !== '' && substr($path, -1) !== '/') $path .= "/";

		$contentsAll = ftp_mlsd($conn, $this->path);
		//$count = 0;
		if ($contentsAll === false) {
			$contentsNames = ftp_nlist($conn, $this->path);
			$contentsAll = [];
			foreach ($contentsNames as $name) {
				$entry = [];
				$entry['name'] = $name;
				$entry['type'] = 'file';
				//$mtime = filemtime("ftp://". $this->username . ":" . $this->password . "@" . $this->host . "/" . $name);
				$mtime = ftp_mdtm($conn, $name);
				$entry['modify'] = $mtime;
				array_push($contentsAll, $entry);
				echo $name . " - Timestamp " . $mtime . "\n";
				//++$count;
				//if ($count > 5) break;
			}
			$path = '';	// Do not prepend path
		}
		
		//die(var_dump($contentsAll));

		// take only files
		foreach ($contentsAll as $key => &$entry) {
			if ($entry['type'] !== 'file') unset($contentsAll[$key]);
		}

		if ($this->newestFileOnly) $contentsAll = $this->newestFile($contentsAll);

		// Take only files which meet the filename pattern
		$contents = [];
		foreach ($contentsAll as $entry) {
			if ($this->filenamePattern !== '' && !fnmatch($this->filenamePattern, $entry['name'])) continue;
			array_push($contents, $path . $entry['name']);
		}

		//die(var_dump($contents));
		//$console->writeLine('There are ' . count($contents) . ' files inside the folder', Console::COLOR_YELLOW);

		foreach ($contents as $content) {
			ftp_get($conn, $localCopyPath . end(explode('/', $content)), $content);
			$resource = new Resource();
			$resource->name = end(explode('/', $content));
			$resource->contents = file_get_contents($localCopyPath . end(explode('/', $content)));
			$resource->meta = [
				"MonitoringPointNCDs" => [], 
				"ObservedPropertySymbols" => [],
				"observedPropertyConversions" => [],
			];
			$results[] = $resource;
		}

		//die(var_dump($results));
		return $results;
	}


	/**
	 * @inheritDoc
	 */
	public static function getName(): string {
		return 'FTP directory transport';
	}


	/**
	 * @inheritDoc
	 */
	public static function getHelp(): string {
		return 'Reads files from a directory through an FTP connection.';
	}


}
