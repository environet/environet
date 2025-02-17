<?php


namespace Environet\Sys\Commands\Tools;

use Environet\Sys\Commands\BaseCommand;
use Environet\Sys\General\Exceptions\PKIException;
use Environet\Sys\General\PKI;

/**
 * Class GenerateSignature
 *
 * Generate a signature based on private key
 *
 * @package Environet\Sys\Commands\Tools
 * @author  SRG Group <dev@srg.hu>
 */
class GenerateSignature extends BaseCommand {


	/**
	 * Run generate signature command.
	 *
	 * Does the following steps:
	 * 1. Prompts for the private key's path (default: /conf/plugins/credentials/private.pem)
	 * 2. Prompts for content input method (write directly in the console or import from file).
	 * 3. Prompts for the content, based on the selected input method.
	 * 4. Asks whether the user wants to hash the content or use as it is.
	 * 5. Generates signature and outputs it to the console.
	 *
	 * @param array $arguments
	 * @param array $options
	 *
	 * @return int
	 * @throws PKIException
	 * @uses \Environet\Sys\General\PKI::generateSignature()
	 */
	public function run($arguments, $options): int {

		$arguments = array_values(array_diff($arguments, ['dist', 'tool', 'sign']));
		$inputContent = null;
		if (count($arguments) === 1) {
			$inputContent = base64_decode($arguments[0]);
		}

		// Get path of private key
		while (true) {
			$keyDefaultLocation = '/conf/plugins/credentials/private.pem';
			if ($inputContent) {
				$keyLocation = $keyDefaultLocation;
			} else {
				$keyLocation = $this->console->askWithDefault('Enter the path of private key (relative to ' . SRC_PATH . '):', $keyDefaultLocation, 200);
			}
			if (!file_exists(SRC_PATH . '/' . ltrim($keyLocation, '/'))) {
				$this->console->writeLine("File $keyLocation does not exist");
				continue;
			}
			// Prepend src path to private key
			$keyLocation = SRC_PATH . '/' . ltrim($keyLocation, '/');
			break;
		}

		if ($inputContent) {
			$mode = 1;
		} else {
			// Get content from file or from input
			$mode = $this->console->askOptions('How do you want to enter the content?', [
				1 => 'Paste / write here (max 500 characters)',
				2 => 'From file'
			]);
		}


		switch ($mode) {
			case 1:
				// Get from input
				$content = $inputContent ?: $this->console->ask('Enter the content from which you want the generate the signature:');
				break;
			case 2:
				// Get from file if exists
				while (true) {
					$contentFile = $this->console->ask('Enter the path of file (relative to ' . SRC_PATH . '):');
					if (!file_exists($contentFile)) {
						$this->console->writeLine("File $contentFile does not exist");
						continue;
					}
					$content = file_get_contents($contentFile);
					break;
				}
		}

		// Get content from file or from input
		if ($inputContent || $this->console->askYesNo('Do you want to generate signature from md5 hash?', false)) {
			$content = md5($content);
		}

		// Generate and write signature
		$pki = new PKI();
		$signature = $pki->generateSignature($content, file_get_contents($keyLocation));

		if ($inputContent) {
			echo $signature;
		} else {
			$this->console->writeLine('Signature:');
			$this->console->writeLineBreak();
			$this->console->writeLine($signature);
		}


		return 0;
	}


}
