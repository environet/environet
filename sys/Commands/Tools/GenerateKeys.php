<?php


namespace Environet\Sys\Commands\Tools;

use Environet\Sys\Commands\BaseCommand;
use Environet\Sys\Commands\Console;
use Environet\Sys\General\PKI;
use Exception;

/**
 * Class GenerateKeys
 *
 * Generate an openssl key pair, and write it to files
 *
 * @package Environet\Sys\Commands\Tools
 * @author  SRG Group <dev@srg.hu>
 */
class GenerateKeys extends BaseCommand {

	/**
	 * @var string Default private key location
	 */
	protected static $keyDefaultLocation = '/conf/plugins/credentials';


	/**
	 * Run generate key pair command.
	 *
	 * Asks for a relative destination path and an optional filename prefix (default is {@see GenerateKeys::$keyDefaultLocation}. Prompts for override if the files already exist.
	 * Creates a 2048 bit long RSA key pair with sha256 algorithm and saves it to the given path. Also creates the destination folder it it doesn't exist.
	 *
	 * @param array $arguments
	 *
	 * @return int
	 */
	public function run($arguments): int {
		$keyDefaultLocation = SRC_PATH . '/conf/plugins/credentials/';
		$keyRealLocation = empty(getenv('ENVIRONET_PLUGIN_CONF_DIR')) ? $keyDefaultLocation : getenv('ENVIRONET_PLUGIN_CONF_DIR') . '/credentials';
		
		$keyLocation = $this->console->askWithDefault('Enter the destination of files (relative to ' . $keyRealLocation . '):', self::$keyDefaultLocation);
		$prefix = $this->console->ask('Enter the prefix for filenames (prefix_private.pem & prefix_public.pem). Prefix is optional');

		$keyLocation = SRC_PATH . '/' . ltrim($keyLocation, '/');
		if (!is_dir($keyLocation)) {
			mkdir($keyLocation, 0755, true);
		}

		$prefix = $prefix ? "{$prefix}_" : '';

		$privateFileName = "{$prefix}private.pem";
		$publicFileName = "{$prefix}public.pem";

		// Continue if files don't exist, or overwrite
		$continue =
			(
				!file_exists("$keyLocation/$privateFileName") || $this->console->askYesNo(
					"Private key file with name $privateFileName under location $keyLocation already exists. Do you want to overwrite it?",
					false
				)
			)
			&&
			(
				!file_exists("$keyLocation/$publicFileName") || $this->console->askYesNo(
					"Public key file with name $publicFileName under location $keyLocation already exists. Do you want to overwrite it?",
					false
				)
			);

		if (!$continue) {
			$this->console->writeLine('Aborting, keep existing key file(s).', Console::COLOR_YELLOW);
			exit(0);
		}

		try {
			[$publicKey, $privateKey] = (new PKI())->generateKeyPair();
		} catch (Exception $e) {
			$this->console->writeLine($e->getMessage(), Console::COLOR_RED);
			exit(1);
		}

		// Write files
		file_put_contents("$keyLocation/$privateFileName", $privateKey);
		file_put_contents("$keyLocation/$publicFileName", $publicKey);

		$this->console->writeLine("Files have been successfully generated to $keyLocation.", Console::COLOR_GREEN);

		return 0;
	}


}
