<?php


namespace Environet\Sys;

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
 * @method string getDatanodeDistHost
 * @method int getOpMode
 * @method bool getStoreInputXmls
 * @method string getTimezone
 * @method string getDatabaseHost
 * @method string getDatabaseDatabase
 * @method string getDatabasePort
 * @method string getDatabaseUser
 * @method string getDatabasePass
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
	 * @uses \Environet\Sys\Config::init()
	 * @uses \Environet\Sys\Config::checkValidity()
	 * @uses \Environet\Sys\Config::setConstants()
	 */
	public function __construct() {
		$this->init();
		$this->checkValidity();
		$this->setConstants();
		self::$instance = $this;
	}


	/**
	 * Init configuration array from defaults and local configs.
	 * @uses \Environet\Sys\Config::isLocalConfigCreated()
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
	 * @uses \Environet\Sys\Config::getDevMode()
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
	 * @uses \Environet\Sys\Config::isLocalConfigCreated()
	 * @uses \Environet\Sys\Config::getTimezone()
	 * @uses \Environet\Sys\Config::getOpMode()
	 */
	public function checkValidity() {
		if (!$this->isLocalConfigCreated()) {
			// Do not throw error if local config is not created, it can be the install script, we have to allow it
			return;
		}
		if (!in_array($this->getTimezone(), timezone_identifiers_list())) {
			throw new InvalidConfigurationException('Timezone is invalid');
		}
		if (!in_array($this->getOpMode(), [EN_OP_MODE_DATA, EN_OP_MODE_DIST, EN_OP_MODE_CLIENT], true)) {
			throw new InvalidConfigurationException('Operation mode is invalid');
		}
	}


	/**
	 * A getter for all properties.
	 * By default a getter looks like this: getGroupConfigName.
	 * Group points to the keys on 1st level in the config array. Config name is the camelCase version of config variable.
	 * The default group is 'environet'.
	 *
	 * @param $name
	 * @param $arguments
	 *
	 * @return mixed
	 * @uses \camelCaseToSnake()
	 * @uses \Environet\Sys\Config::processValue()
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
			if ($group && $configName) {
				// Get and process value
				return $this->processValue($this->config[$group][$configName] ?? null, $configName);
			}
		}
	}


	/**
	 * Check if local config already created
	 * @return bool
	 */
	protected function isLocalConfigCreated(): bool {
		return file_exists(self::$localIniPath);
	}


	/**
	 * Process config value
	 *
	 * @param mixed  $value
	 * @param string $configName
	 *
	 * @return string
	 */
	protected function processValue($value, $configName) {
		if (is_string($value) && preg_match('/_path$/', $configName)) {
			//Options endign with _path will be prefixed with the SRC path to make an absolute path.
			return SRC_PATH . '/' . ltrim($value, '/');
		}

		return $value;
	}


	/**
	 * Get valid connection string for SQL connection based on the config
	 *
	 * @return string
	 */
	public function getSqlDsn(): string {
		$host = $this->config['database']['host'] ?? 'localhost';
		$port = $this->config['database']['port'] ?? 5432;
		$database = $this->config['database']['database'] ?? null;
		$user = $this->config['database']['user'] ?? null;
		$pass = $this->config['database']['pass'] ?? null;

		return "pgsql:host=$host;port=$port;dbname=$database;user=$user;password=$pass";
	}


}
