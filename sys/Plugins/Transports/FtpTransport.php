<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\BuilderLayerInterface;
use Environet\Sys\Plugins\PluginBuilder;
use Environet\Sys\Plugins\Resource;
use Environet\Sys\Plugins\TransportInterface;
use Environet\Sys\Plugins\WithConversionsConfigTrait;
use Exception;
use Throwable;

/**
 * Class FtpTransport
 *
 * Transport layer for importing data from a FTP directory.
 *
 * @package Environet\Sys\Plugins\Transports
 * @author  SRG Group <dev@srg.hu>
 */
class FtpTransport extends AbstractTransport {

	use WithConversionsConfigTrait;

	/**
	 * @var bool
	 */
	private $secure;

	/**
	 * @var string
	 */
	private $host;

	/**
	 * @var int|null
	 */
	private $port = null;

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
	 * @var int
	 */
	private $lastNDaysOnly;

	/**
	 * @var mixed
	 */
	private $skipProcessed;

	/**
	 * @var string
	 */
	private $conversionsFilename;


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console, PluginBuilder $builder): TransportInterface {
		$console->writeLine('');
		$console->writeLine('Configuring FTP transport', Console::COLOR_YELLOW);

		$monitoringPointType = self::createMonitoringPointTypeConfig($console);

		$console->writeLine('FTP host:');
		$host = $console->ask('');

		$console->writeLine('FTP port:');
		$console->write('Leave empty to use the default 21.');
		$port = $console->ask('');

		$console->writeLine('Secure connection:');
		$secure = $console->askWithDefault('Enter 0 for an FTP connection without SSL', true);

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

		$console->writeLine('Use only files with modification time newer than or equal N days:');
		$console->write('Enter a number > 0 for N or 0 to use all files:');
		$lastNDaysOnly = $console->askWithDefault('', 0);

		$console->writeLine('Do you want to skip already processed and successfully uploaded files?');
		$console->write('Enter 1 if processed files shouldn\'t be downloaded again:');
		$skipProcessed = $console->askWithDefault('', false);

		$console->writeLine('Do you want to user a conversion specification?');
		$console->write('Enter 1 if to specify the conversion specification file:');
		$withConversions = $console->askWithDefault('', '0');

		$conversionsFilename = null;
		if ($withConversions == 1) {
			$conversionsFilename = $console->ask('Filename of conversion specifications');
		}

		$config = [
			'host'                => $host,
			'port'                => $port ?: null,
			'secure'              => $secure,
			'username'            => $username,
			'password'            => $password,
			'path'                => $path,
			'filenamePattern'     => $filenamePattern,
			'newestFileOnly'      => $newestFileOnly,
			'lastNDaysOnly'       => $lastNDaysOnly,
			'skipProcessed'       => $skipProcessed,
			'conversionsFilename' => $conversionsFilename,
			'monitoringPointType' => $monitoringPointType,
		];

		return new self($config);
	}


	/**
	 * @inheritDoc
	 */
	public function serializeConfiguration(): string {
		return 'host = "' . $this->host . '"' . "\n"
			. 'port = ' . ($this->port ? (int) $this->port : '') . '' . "\n"
			. 'secure = "' . $this->secure . '"' . "\n"
			. 'username = "' . $this->username . '"' . "\n"
			. 'password = "' . $this->password . '"' . "\n"
			. 'path = "' . $this->path . '"' . "\n"
			. 'filenamePattern = "' . $this->filenamePattern . '"' . "\n"
			. 'newestFileOnly = "' . $this->newestFileOnly . '"' . "\n"
			. 'lastNDaysOnly = "' . $this->lastNDaysOnly . '"' . "\n"
			. 'skipProcessed = "' . $this->skipProcessed . '"' . "\n"
			. 'conversionsFilename = "' . $this->conversionsFilename . '"' . "\n"
			. 'monitoringPointType = "' . $this->monitoringPointType . '"' . "\n";
	}


	/**
	 * FtpDirectoryTransport constructor.
	 *
	 * @param array $config
	 */
	public function __construct(array $config) {
		$this->host = $config['host'];
		$this->port = isset($config['port']) && !empty($config['port']) ? (int) $config['port'] : null;
		$this->secure = $config['secure'];
		$this->username = $config['username'];
		$this->password = $config['password'];
		$this->path = rtrim($config['path'], '/');
		$this->filenamePattern = $config['filenamePattern'];
		$this->newestFileOnly = $config['newestFileOnly'];
		$this->lastNDaysOnly = $config['lastNDaysOnly'];
		$this->skipProcessed = $config['skipProcessed'] ?? false;
		$this->conversionsFilename = $config['conversionsFilename'];
		parent::__construct($config);
	}


	/**
	 * @inheritDoc
	 * @throws Exception
	 * @see Resource
	 */
	public function get(Console $console, string $configFile): array {
		$configuration = preg_replace('/^(.*)\.[^\.]+$/i', '$1', $configFile);
		$localFileDir = $this->getLocalFileDir($configuration);
		$localCopyPath = rtrim($localFileDir . '/' . $this->path, '/');

		if (!file_exists($localCopyPath)) {
			//Create local dir if doesn't exist
			mkdir($localCopyPath, 0755, true);
		}

		//Connect to FTP with username and password
		$port = $this->port ?: 21;
		$conn = $this->secure ? @ftp_ssl_connect($this->host, $port) : @ftp_connect($this->host, $port);
		if ($conn == false) {
			throw new Exception("Connection to ftp server " . $this->host . " failed");
		}

		$login_result = @ftp_login($conn, $this->username, $this->password);
		if ($login_result) {
			$console->writeLine('Logged in to ftp server', Console::COLOR_YELLOW);
		} else {
			throw new Exception("Login to ftp server " . $this->host . " failed");
		}
		ftp_pasv($conn, true);
		ftp_set_option($conn, FTP_USEPASVADDRESS, false);

		//Get list of files under directory
		$files = $this->getListOfFiles($conn, $this->path, $console);

		//Filter to filename pattern
		if (!empty($files) && $this->filenamePattern) {
			$files = array_filter($files, function ($file) {
				return fnmatch($this->filenamePattern, $file['name']);
			});
		}
		$console->writeLog(sprintf('Found %s files available on server.', count($files)));

		//Filter to newest file only
		if (!empty($files) && $this->newestFileOnly) {
			usort($files, function ($a, $b) {
				if (!empty($a['modify']) && !empty($b['modify'])) {
					return $a['modify'] > $b['modify'] ? - 1 : 1;
				}

				return 0;
			});
			$files = [$files[0]];
		}

		// Filter to date within last N days
		if (!empty($files) && $this->lastNDaysOnly > 0) {
			$newFiles = [];
			foreach ($files as $file) {
				if (!empty($file['modify'])) {
					$dateFile = new \DateTime();
					$dateFile->setTimestamp($file['modify']);
					$dateNow = new \DateTime();
					$interval = date_diff($dateFile, $dateNow);
					$days = $interval->format('%a');
					if ($days <= $this->lastNDaysOnly) {
						array_push($newFiles, $file);
					}
				}
			}
			$files = $newFiles;
		}
		$console->writeLog(sprintf('Filtered to %s relevant files.', count($files)));

		if ($this->skipProcessed) {
			$processedFiles = $this->getProcessedFiles($configuration);
			$files = array_filter($files, function ($file) use ($processedFiles) {
				return !in_array($file['name'], $processedFiles);
			});
		}
		$console->writeLog(sprintf('Skipping files already processed, processing %s files.', count($files)));

		//Prepend path the filename
		$files = array_map(function ($file) {
			return $this->path . '/' . $file['name'];
		}, $files);

		//Create resources base on files
		$results = [];
		foreach ($files as $file) {
			$filename = basename($file);
			ftp_get($conn, $localCopyPath . '/' . $filename, $file);
			$resource = new Resource();
			$resource->setName($filename);
			$resource->setContents(file_get_contents($localCopyPath . '/' . $filename));
			$resource->setLocalCopyPath($localCopyPath . '/' . $filename);

			if ($this->conversionsFilename) {
				//Add some meta information if a conversion filename is specified
				$resource->setObservedPropertyConversions($this->getConversionsConfig()['observedPropertyConversions'] ?? []);
				$resource->setKeepExtraData(true);
			}

			$results[] = $resource;
		}

		return $results;
	}


	/**
	 * Get list of files with MLSD or NLIST
	 *
	 * @param         $connection
	 * @param string     $path
	 * @param Console    $console
	 *
	 * @return array|array[]
	 */
	protected function getListOfFiles($connection, string $path, Console $console): array {
		$wasErrorWarning = false;
		set_error_handler(function (int $errNo, string $errStr) use (&$wasErrorWarning) {
			$wasErrorWarning = $errStr;
		});

		//Try with MLSD
		$files = ftp_mlsd($connection, $path);

		if ($files === false) {
			//FTP server is not compatible with MLSD, try with NLIST, and create a "compatible" array for each item
			$files = ftp_nlist($connection, $path);

			if ($files !== false) {
				$files = array_map(function ($filename) use ($connection, $path) {
					return [
						'name'   => basename($filename),
						'type'   => 'file',
						'modify' => ftp_mdtm($connection, $filename)
					];
				}, $files);
			}
		}

		//Filter only files
		if (is_array($files)) {
			$files = array_filter($files, function ($file) {
				return isset($file['type']) && $file['type'] === 'file';
			});
		}

		if ($files === false && $wasErrorWarning) {
			$console->writeLine('No files were found, warning/error happened while getting files: ' . $wasErrorWarning, Console::COLOR_YELLOW);

			return [];
		}

		restore_error_handler();

		return $files;
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
