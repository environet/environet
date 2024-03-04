<?php


namespace Environet\Sys\Commands\DataNode;

use Environet\Sys\Commands\BaseCommand;
use Environet\Sys\Commands\Console;
use RecursiveIteratorIterator;
use SplFileInfo;
use stdClass;

/**
 * Class MigrateConfig
 *
 * Run config file migrations
 *
 * @package Environet\Sys\Commands\DataNode
 * @author  SRG Group <dev@srg.hu>
 */
class MigrateConfig extends BaseCommand {


	/**
	 * Run the initialization command.
	 *
	 * The command does the following steps:
	 *
	 * @param array $arguments
	 *
	 * @return int
	 */
	public function run($arguments): int {
		$selectedConfigFile = $arguments[3] ?? null;

		$migrations = [
			'removeUrlPattern',
			'removeGeneralInformation',
			'moveMonitoringPointType',
		];
		ini_set('memory_limit', - 1);

		//Run each migration, and log results
		$mainExitCode = 0;
		foreach ($migrations as $migration) {
			$output = [];
			$exitCode = $this->{$migration}($output, $selectedConfigFile);

			if ($exitCode === - 1) {
				//Already migrated
				$this->console->writeLine("$migration: Already migrated", Console::COLOR_YELLOW);
			} elseif ($exitCode > 0) {
				//Error during migration
				$this->console->writeLine("$migration: Errors:", Console::COLOR_RED);
				foreach ($output as $item) {
					$this->console->writeLine("$item");
				}
				$mainExitCode = 1;
			} else {
				//Success
				$this->console->writeLine("$migration: Done", Console::COLOR_GREEN);
			}
			$this->console->writeLineBreak();
		}

		return $mainExitCode;
	}


	/**
	 * Remove urlPattern from config, and move it to ini config
	 *
	 * @param array       $output
	 * @param string|null $selectedConfigFile
	 *
	 * @return int
	 */
	private function removeUrlPattern(array &$output, ?string $selectedConfigFile = null): int {
		$return = - 1;

		$configs = $this->getConfigurations($selectedConfigFile);

		foreach ($configs as $config) {
			if ($selectedConfigFile && preg_match('/' . preg_quote($selectedConfigFile, '/') . '$/i', $config['ini']) === 0) {
				continue;
			}
			if (empty($config['conversionsContent']['generalInformation']['URLPattern'])) {
				continue;
			}
			$this->console->writeLine("Migrating urlPattern in config: " . $config['ini'], Console::COLOR_YELLOW);
			$urlPattern = $config['conversionsContent']['generalInformation']['URLPattern'];
			unset($config['conversionsContent']['generalInformation']['URLPattern']);
			if (empty($config['conversionsContent']['generalInformation'])) {
				$config['conversionsContent']['generalInformation'] = new stdClass();
			}
			$config['iniContent']['transport']['url'] = $urlPattern;

			$this->writeJsonConfig($config['conversionsContent'], $config['conversions']);
			$this->writeIniConfig($config['iniContent'], $config['ini']);
			$this->console->writeLine("Config migrated successfully: " . $config['ini'], Console::COLOR_GREEN);
			$return = 0;
		}

		return $return;
	}


	/**
	 * Remove generalInformation from config
	 *
	 * @param array       $output
	 * @param string|null $selectedConfigFile
	 *
	 * @return int
	 */
	private function removeGeneralInformation(array &$output, ?string $selectedConfigFile = null): int {
		$return = - 1;

		$configs = $this->getConfigurations($selectedConfigFile);

		foreach ($configs as $config) {
			if (!is_array($config['conversionsContent']) || !array_key_exists('generalInformation', $config['conversionsContent'])) {
				continue;
			}
			$this->console->writeLine("Migrating generalInformation in config: " . $config['ini'], Console::COLOR_YELLOW);
			unset($config['conversionsContent']['generalInformation']);

			$this->writeJsonConfig($config['conversionsContent'], $config['conversions']);
			$this->console->writeLine("Config migrated successfully: " . $config['ini'], Console::COLOR_GREEN);
			$return = 0;
		}

		return $return;
	}


	/**
	 * Move monitoringPointType from conversions to ini
	 *
	 * @param array       $output
	 * @param string|null $selectedConfigFile
	 *
	 * @return int
	 */
	private function moveMonitoringPointType(array &$output, ?string $selectedConfigFile = null): int {
		$return = - 1;

		$configs = $this->getConfigurations($selectedConfigFile);

		foreach ($configs as $config) {
			if (!is_array($config['conversionsContent']) || !array_key_exists('monitoringPointType', $config['conversionsContent'])) {
				continue;
			}
			$this->console->writeLine("Migrating monitoringPointType in config: " . $config['ini'], Console::COLOR_YELLOW);
			$monitoringPointType = $config['conversionsContent']['monitoringPointType'];
			unset($config['conversionsContent']['monitoringPointType']);
			if (!empty(trim($monitoringPointType))) {
				$config['iniContent']['transport']['monitoringPointType'] = $monitoringPointType;
				$config['iniContent']['parser']['monitoringPointType'] = $monitoringPointType;
			}

			$this->writeJsonConfig($config['conversionsContent'], $config['conversions']);
			$this->writeIniConfig($config['iniContent'], $config['ini']);
			$this->console->writeLine("Config migrated successfully: " . $config['ini'], Console::COLOR_GREEN);
			$return = 0;
		}

		return $return;
	}


	/**
	 * @param string|null $selectedConfigFile
	 *
	 * @return array
	 */
	private function getConfigurations(?string $selectedConfigFile = null): array {
		$iterator = new RecursiveIteratorIterator(new \RecursiveDirectoryIterator(CONFIGURATION_PATH), RecursiveIteratorIterator::LEAVES_ONLY);
		$configurations = [];
		/** @var SplFileInfo $file */
		foreach ($iterator as $file) {
			$configuration = [];
			if ($file->isDir()) {
				continue;
			}
			$filename = $file->getFilename();
			$extension = pathinfo($filename, PATHINFO_EXTENSION);
			if (!in_array($extension, ['conf', ''])) {
				continue;
			}

			if ($selectedConfigFile && preg_match('/' . preg_quote($selectedConfigFile, '/') . '$/i', $file->getPathname()) === 0) {
				continue;
			}

			$configuration['ini'] = $file->getPathname();
			$iniConfig = parse_ini_file($file->getPathname(), true);
			if ($iniConfig === false) {
				continue;
			}
			$configuration['iniContent'] = $iniConfig;

			$conversionsFilename = trim($iniConfig['transport']['conversionsFilename'] ?? '');
			if (!empty($conversionsFilename) &&
				file_exists(($conversionFile = CONFIGURATION_PATH . '/' . $conversionsFilename))
			) {
				$configuration['conversions'] = $conversionFile;
				$configuration['conversionsContent'] = json_decode(file_get_contents($conversionFile), true);
			}

			$formatsFilename = trim($iniConfig['parser']['formatsFilename'] ?? '');
			if (!empty($formatsFilename) &&
				file_exists(($formatsFile = CONFIGURATION_PATH . $formatsFilename))
			) {
				$configuration['formats'] = $formatsFile;
				$configuration['formatsContent'] = json_decode(file_get_contents($formatsFile), true);
			}

			$configurations[] = $configuration;
		}

		return $configurations;
	}


	/**
	 * @param array  $iniContent
	 * @param string $targetFile
	 *
	 * @return void
	 */
	private function writeIniConfig(
		array $iniContent,
		string $targetFile
	) {
		$ini = '';
		foreach ($iniContent as $section => $values) {
			$ini .= "[$section]\n";
			foreach ($values as $key => $value) {
				if ($key === 'className') {
					$ini .= "$key = $value\n";
				} else {
					$ini .= "$key = \"$value\"\n";
				}
			}
			$ini .= "\n";
		}

		$this->writeFile($ini, $targetFile);
	}


	/**
	 * @param array  $jsonContent
	 * @param string $targetFile
	 *
	 * @return void
	 */
	private function writeJsonConfig(
		array $jsonContent,
		string $targetFile
	) {
		$this->writeFile(json_encode($jsonContent, JSON_PRETTY_PRINT), $targetFile);
	}


	/**
	 * @param string $contents
	 * @param string $targetFile
	 *
	 * @return void
	 */
	private function writeFile(
		string $contents,
		string $targetFile
	) {
		if (file_exists($targetFile)) {
			$bakFile = $targetFile . '.bak';
			$i = 1;
			while (file_exists($bakFile)) {
				$bakFile = $targetFile . '.bak.' . $i;
				$i ++;
			}
			copy($targetFile, $bakFile);
		}
		file_put_contents($targetFile, $contents);
	}


}
