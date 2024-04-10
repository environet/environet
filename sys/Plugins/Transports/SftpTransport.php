<?php

namespace Environet\Sys\Plugins\Transports;

use Environet\Sys\Commands\Console;
use Environet\Sys\Plugins\PluginBuilder;
use Environet\Sys\Plugins\Resource;
use Environet\Sys\Plugins\TransportInterface;
use Environet\Sys\Plugins\WithConversionsConfigTrait;
use Exception;

/**
 * Class SftpTransport
 *
 * Transport layer for importing data from a SFTP directory.
 *
 * @package Environet\Sys\Plugins\Transports
 * @author  SRG Group <dev@srg.hu>
 */
class SftpTransport extends AbstractTransport {

	use WithConversionsConfigTrait;

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
	private $authMode;

	/**
	 * @var string
	 */
	private $password;

	/**
	 * @var string
	 */
	private $privateKeyPath;

	/**
	 * @var string
	 */
	private $publicKeyPath;

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
	 * @var string
	 */
	private $conversionsFilename;


	/**
	 * @inheritDoc
	 */
	public static function create(Console $console, PluginBuilder $builder): TransportInterface {
		$console->writeLine('');
		$console->writeLine('Configuring SFTP transport', Console::COLOR_YELLOW);

		$monitoringPointType = self::createMonitoringPointTypeConfig($console);

		$console->writeLine('SFTP host:');
		$host = $console->ask('');

		$console->writeLine('SFTP port:');
		$console->write('Leave empty to use the default 22.');
		$port = $console->ask('');

		$console->writeLine('SFTP username:');
		$username = $console->ask('');

		$console->writeLine('SFTP auth mode (password or keypair)');
		$console->write('Enter 1 for password authentification, 2 for keypair authentification:');
		$authMode = $console->ask('');
		$authMode = $authMode == '1' ? 'password' : 'keypair';

		if ($authMode == 'password') {
			$console->writeLine('SFTP password:');
			$password = $console->ask('');
		} else {
			$console->writeLine(
				"Enter the path to the private key to be used for authentication. This should be a path relative to '/conf/plugins/credentials'.",
				Console::COLOR_YELLOW
			);
			$console->writeLine(
				"For example: If you placed your private key into conf/plugins/credentials/privatekey.pem, you would enter 'privatekey.pem'",
				Console::COLOR_YELLOW
			);
			$privateKeyPath = $console->ask("SFTP private key path:");

			$console->writeLine(
				"Enter the path to the public key to be used for authentication. This should be a path relative to '/conf/plugins/credentials'.",
				Console::COLOR_YELLOW
			);
			$publicKeyPath = $console->askWithDefault("SFTP public key path:", $privateKeyPath . '.pub');
		}

		$console->writeLine('Enter path to the directory where the data files are located on the SFTP server.');
		$console->write('Leave empty if the data files are in the SFTP root directory.');
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
			'authMode'            => $authMode,
			'username'            => $username,
			'password'            => $password ?? null,
			'privateKeyPath'      => $privateKeyPath ?? null,
			'publicKeyPath'       => $publicKeyPath ?? null,
			'path'                => $path,
			'filenamePattern'     => $filenamePattern,
			'newestFileOnly'      => $newestFileOnly,
			'lastNDaysOnly'       => $lastNDaysOnly,
			'conversionsFilename' => $conversionsFilename,
			'monitoringPointType' => $monitoringPointType ?: null,
		];

		return new self($config);
	}


	/**
	 * @inheritDoc
	 */
	public function serializeConfiguration(): string {
		return 'host = "' . $this->host . '"' . "\n"
			. 'port = ' . ($this->port ? (int) $this->port : '') . '' . "\n"
			. 'authMode = "' . $this->authMode . '"' . "\n"
			. 'username = "' . $this->username . '"' . "\n"
			. 'password = "' . $this->password . '"' . "\n"
			. 'privateKeyPath = "' . $this->privateKeyPath . '"' . "\n"
			. 'publicKeyPath = "' . $this->publicKeyPath . '"' . "\n"
			. 'path = "' . $this->path . '"' . "\n"
			. 'filenamePattern = "' . $this->filenamePattern . '"' . "\n"
			. 'newestFileOnly = "' . $this->newestFileOnly . '"' . "\n"
			. 'lastNDaysOnly = "' . $this->lastNDaysOnly . '"' . "\n"
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
		$this->authMode = $config['authMode'];
		$this->username = $config['username'];
		$this->password = $config['password'];
		$this->privateKeyPath = $config['privateKeyPath'];
		$this->publicKeyPath = $config['publicKeyPath'];
		$this->path = rtrim($config['path'], '/');
		$this->filenamePattern = $config['filenamePattern'];
		$this->newestFileOnly = $config['newestFileOnly'];
		$this->lastNDaysOnly = $config['lastNDaysOnly'];
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
		$localFileDir = SRC_PATH . '/data/plugin_input_files/' . $configuration . '/';
		$localCopyPath = rtrim($localFileDir, '/');

		if (!file_exists($localCopyPath)) {
			//Create local dir if doesn't exist
			mkdir($localCopyPath, 0755, true);
		}

		//Connect to SFTP with username and password
		$port = $this->port ?: 22;
		$conn = ssh2_connect($this->host, $port);
		if ($this->authMode == 'keypair') {
			//Connect to SFTP with username and private key
			$privateKeyPath = SRC_PATH . "/conf/plugins/credentials/{$this->privateKeyPath}";
			$publicKeyPath = SRC_PATH . "/conf/plugins/credentials/{$this->publicKeyPath}";
			chmod($privateKeyPath, 0600);

			$privateKeyContents = file_get_contents($privateKeyPath);
			if (strpos($privateKeyContents, 'BEGIN OPENSSH PRIVATE KEY') !== false) {
				$privateKeyPathRsa = $privateKeyPath . '.rsa';
				if (!file_exists($privateKeyPathRsa)) {
					copy($privateKeyPath, $privateKeyPathRsa);
					chmod($privateKeyPathRsa, 0600);
					//Convert private key to RSA format
					exec("ssh-keygen -p -N '' -m pem -f {$privateKeyPathRsa}");
				}
				$privateKeyPath = $privateKeyPathRsa;
			}
			ssh2_auth_pubkey_file($conn, $this->username, $publicKeyPath, $privateKeyPath);
		} else {
			//Connect to SFTP with username and password
			ssh2_auth_password($conn, $this->username, $this->password);
		}

		if ($conn === false) {
			throw new Exception("Connection to sftp server " . $this->host . " failed: ");
		}

		$sftp = ssh2_sftp($conn);
		if ($sftp === false) {
			throw new Exception("Connection to sftp server " . $this->host . " failed");
		}

		$console->writeLine('Connected to sftp server ' . $this->host . ' on port ' . $port, Console::COLOR_YELLOW);

		$sftpId = intval($sftp);

		$path = '/';
		if ($this->path) {
			$path .= trim($this->path, '/');
		}

		$files = [];
		$dirPath = "ssh2.sftp://" . $sftpId . $path;
		if ($dirHandler = opendir($dirPath)) {
			$files = [];
			while (($file = readdir($dirHandler)) !== false) {
				if ($this->filenamePattern && !fnmatch($this->filenamePattern, $file)) {
					continue;
				}
				$files[] = [
					'name'   => basename($file),
					'type'   => 'file',
					'modify' => filemtime($dirPath . '/' . $file)
				];
			}
			closedir($dirHandler);
		}


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

		//Create resources base on files
		$results = [];
		foreach ($files as $file) {
			copy($dirPath . '/' . $file['name'], $localCopyPath . '/' . $file['name']);
			$resource = new Resource();
			$resource->setName($file['name']);
			$resource->setContents(file_get_contents($localCopyPath . '/' . $file['name']));

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
	 * @inheritDoc
	 */
	public static function getName(): string {
		return 'SFTP directory transport';
	}


	/**
	 * @inheritDoc
	 */
	public static function getHelp(): string {
		return 'Reads files from a directory through an SFTP connection.';
	}


}
