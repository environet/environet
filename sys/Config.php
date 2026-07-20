<?php


namespace Environet\Sys;

use BadMethodCallException;
use Environet\Sys\General\Exceptions\InvalidConfigurationException;

/**
 * Class Config
 *
 * A singleton configuration class which can process ini configurations, validate it, and makes some getters.
 *
 * @method string getErrorDebugPath
 * @method string getErrorExceptionPath
 * @method bool getErrorDebugEnable
 * @method bool getErrorFileDebugEnable
 * @method bool getDevMode
 * @method string getDistnodeInternalApiHost
 * @method int getOpMode
 * @method bool getStoreDistributionNodePayloads
 * @method bool getStoreDataNodePayloads
 * @method string getTimezone
 * @method string getDatabaseHost
 * @method string getDatabaseDatabase
 * @method string getDatabasePort
 * @method string getDatabaseUser
 * @method string getDatabasePass
 * @method string|null getExportTitle
 * @method string|null getExportAuthor
 * @method string getExportPropertyTypeLabelRealTime
 * @method string getExportPropertyTypeLabelProcessed
 *
 * @package Environet\Sys
 * @author  SRG Group <dev@srg.hu>
 */
class Config {


	/**
	 * @var Config|null The instance for singleton behavior
	 */
	protected static $instance = null;

	/**
	 * @var string The default config file. It doesn't contain every option, so by default local config is required.
	 */
	protected static $defaultsIniPath = SRC_PATH . '/sys/conf.default.ini';

	/**
	 * @var string A local config file. The config array will be extended with this
	 */
	protected static $localIniPath = SRC_PATH . '/conf/conf.local.ini';

	/**
	 * @var array The configuration array, with 2 level
	 */
	private $config;


	/**
	 * Config constructor.
	 * It reads the ini files, check the configuration validity, and set some global constants.
	 *
	 * @throws InvalidConfigurationException
	 * @uses Config::init
	 * @uses Config::checkValidity
	 * @uses Config::setConstants
	 */
	public function __construct() {
		$this->init();
		$this->checkValidity();
		$this->setConstants();
		self::$instance = $this;
	}


	/**
	 * Init configuration array from defaults and local configs.
	 * @uses Config::isLocalConfigCreated
	 */
	public function init(): void {
		$this->config = parse_ini_file(self::$defaultsIniPath, true, INI_SCANNER_TYPED);
		if ($this->isLocalConfigCreated()) {
			$localIni = parse_ini_file(self::$localIniPath, true, INI_SCANNER_TYPED);
			$this->config = array_replace_recursive($this->config, $localIni);
		}
	}


	/**
	 * Set some frequently used options and global constants.
	 * @uses Config::getDevMode
	 */
	public function setConstants(): void {
		defined('EN_DEV_MODE') || define("EN_DEV_MODE", $this->getDevMode());
	}


	/**
	 * Singleton behavior
	 *
	 * @return Config|null
	 */
	public static function getInstance() {
		return self::$instance;
	}


	/**
	 * Check if configuration is valid, and throw an exception if not.
	 *
	 * @throws InvalidConfigurationException
	 * @uses Config::isLocalConfigCreated
	 * @uses Config::getTimezone
	 * @uses Config::getOpMode
	 */
	public function checkValidity() {
		if (!$this->isLocalConfigCreated()) {
			// Do not throw error if local config is not created, it can be the install script, we have to allow it
			return;
		}
		if (!in_array($this->getTimezone(), timezone_identifiers_list())) {
			throw new InvalidConfigurationException('Timezone is invalid');
		}
		if (!in_array($this->getOpMode(), [EN_OP_MODE_DATA, EN_OP_MODE_DIST], true)) {
			throw new InvalidConfigurationException('Operation mode is invalid');
		}
	}


	/**
	 * Get the configured license text.
	 *
	 * Supports the multi-line INI syntax where continuation lines are indented
	 * with tabs/spaces. Those leading indentation characters are stripped so
	 * they do not leak into the generated CSV/XLSX/XML output.
	 */
	public function getLicenseText(): string {
		$value = $this->config['environet']['license_text'] ?? '';
		if (!is_string($value) || $value === '') {
			return '';
		}
		// Normalize line endings and strip leading whitespace (tabs/spaces) from each line after a newline.
		$value = str_replace(["\r\n", "\r"], "\n", $value);
		$value = preg_replace('/\n[ \t]+/', "\n", $value);
		return trim($value);
	}


	/**
	 * A getter for all properties.
	 * By default a getter looks like this: getGroupConfigName.
	 * Group points to the keys on 1st level in the config array. Config name is the camelCase version of config variable.
	 * The default group is 'environet'.
	 *
	 *
	 * @return mixed
	 * @uses \camelCaseToSnake()
	 * @uses Config::processValue
	 */
	public function __call($name, $arguments) {
		if (preg_match('/^get(\w+)/', $name, $match)) {
			// A valid getter pattern

			// Get prefixes based on the array keys. If no group is defined in the getter, environet will be used
			$prefixes = array_keys($this->config);
			$group = 'environet';

			// Convert config name to snake case
			$configName = camelCaseToSnake($match[1]);
			$configNameExploded = explode('_', $configName);
			if (in_array(reset($configNameExploded), $prefixes)) {
				// Found a valid group in the first part of the config name, so cut it and use as group
				$group = array_shift($configNameExploded);
			}
			$configName = implode('_', $configNameExploded);

			// Backward compatibility: map old config names to new ones
			$configMigrations = [
				'store_distribution_node_payloads' => 'store_input_xmls',
			];

			if ($group && $configName) {
				// Get and process value
				$value = $this->config[$group][$configName] ?? null;

				// If value is null and this is a migrated config, check for old name
				if ($value === null && isset($configMigrations[$configName])) {
					$oldConfigName = $configMigrations[$configName];
					$value = $this->config[$group][$oldConfigName] ?? null;
				}

				return $this->processValue($value, $configName);
			}
		}

		throw new BadMethodCallException("Method $name does not exist");
	}


	/**
	 * Check if local config already created
	 */
	protected function isLocalConfigCreated(): bool {
		return file_exists(self::$localIniPath);
	}


	/**
	 * Process config value
	 *
	 * @param mixed $value
	 * @param string $configName
	 *
	 * @return string
	 */
	protected function processValue($value, $configName) {
		if (is_string($value) && str_ends_with($configName, '_path')) {
			//Options endign with _path will be prefixed with the SRC path to make an absolute path.
			return SRC_PATH . '/' . ltrim((string) $value, '/');
		}

		return $value;
	}


	/**
	 * Get valid connection string for SQL connection based on the config
	 *
	 */
	public function getSqlDsn(): string {
		$host = $this->config['database']['host'] ?? 'localhost';
		$port = $this->config['database']['port'] ?? 5432;
		$database = $this->config['database']['database'] ?? null;
		$user = $this->config['database']['user'] ?? null;
		$pass = $this->config['database']['pass'] ?? null;

		return "pgsql:host=$host;port=$port;dbname=$database;user=$user;password=$pass";
	}


	public function getUploadMaxSize(): string {
		return $this->config['environet']['upload_max_size'] ?? '2M';
	}


	public function getUploadMaxSizeInBytes(): int {
		$maxSize = $this->getUploadMaxSize();
		if (preg_match('/^(\d+)([kMG])$/', $maxSize, $match)) {
			return match ($match[2]) {
				'k' => $match[1] * 1024,
				'M' => $match[1] * 1024 * 1024,
				'G' => $match[1] * 1024 * 1024 * 1024,
				default => $match[1],
			};
		}

		return $maxSize;
	}


}
