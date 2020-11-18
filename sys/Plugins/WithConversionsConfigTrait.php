<?php

namespace Environet\Sys\Plugins;

use Exception;

/**
 * Trait WithConversionsConfigTrait
 * @package Environet\Sys\Plugins
 */
trait WithConversionsConfigTrait {

	/**
	 * @var array
	 */
	protected $conversionsConfig;


	/**
	 * Get conversions from the JSON config
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function getConversionsConfig(): array {
		if (is_null($this->conversionsConfig)) {
			$conversionsPathname = CONFIGURATION_PATH . '/' . $this->conversionsFilename; //Path of file is in a fixed location
			if (!(file_exists($conversionsPathname) && //File must be existing
				  ($conversions = file_get_contents($conversionsPathname)) && //File must be not-empty and readable
				  ($conversions = json_decode($conversions, true)) //Decode to json
			)) {
				throw new Exception("Syntax error in json string of conversions configuration file '$conversionsPathname', or file does not exist.");
			}
			$this->conversionsConfig = $conversions;
		}
		return $this->conversionsConfig;
	}


}
