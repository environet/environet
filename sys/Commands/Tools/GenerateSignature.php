<?php


namespace Environet\Sys\Commands\Tools;

use Environet\Sys\Commands\BaseCommand;
use Environet\Sys\General\Exceptions\PKIException;
use Environet\Sys\General\PKI;

/**
 * Class GenerateSignature
 *
 * Generate a signatue based on private key
 *
 * @package Environet\Sys\Commands\DataNode
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class GenerateSignature extends BaseCommand {


	/**
	 * @inheritDoc
	 * @throws PKIException
	 */
	public function run($arguments): int {

		//Get path of private key
		while (true) {
			$keyDefaultLocation = '/conf/plugins/credentials/private.pem';
			$keyLocation = $this->console->askWithDefault("Enter the path of private key (relative to " . SRC_PATH . "):", $keyDefaultLocation, 200);
			if (!file_exists(SRC_PATH . '/' . ltrim($keyLocation, '/'))) {
				$this->console->writeLine("File $keyLocation does not exist");
				continue;
			}
			//Prepend src path to private key
			$keyLocation = SRC_PATH . '/' . ltrim($keyLocation, '/');
			break;
		}

		//Get content from file or from input
		$mode = $this->console->askOptions("How do you want to enter the content?", [
			1 => 'Paste / write here (max 500 characters)',
			2 => 'From file'
		]);

		switch ($mode) {
			case 1:
				//Get from input
				$content = $this->console->ask("Enter te content from which you want the generate the signature:", 501);
				break;
			case 2:
				//GEt from file if exists
				while (true) {
					$contentFile = $this->console->ask("Enter the path of file (relative to " . SRC_PATH . "):", 200);
					if (!file_exists($contentFile)) {
						$this->console->writeLine("File $contentFile does not exist");
						continue;
					}
					$content = file_get_contents($contentFile);
					break;
				}
		}

		//Generate and write signature
		$pki = new PKI();
		$signature = $pki->generateSignature($content, file_get_contents($keyLocation));

		$this->console->writeLine("Signature:");
		$this->console->writeLineBreak();
		$this->console->writeLine($signature);

		return 0;
	}


}
