<?php


namespace Environet\Sys\Commands;

use Environet\Sys\Commands\Exceptions\CommandException;

/**
 * Class BaseCommand
 *
 * @package Environet\Sys\Commands
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
abstract class BaseCommand {

	/**
	 * @var Console
	 */
	protected $console;


	/**
	 * BaseCommand constructor.
	 *
	 * @param Console $console
	 */
	public function __construct(Console $console) {
		$this->console = $console;
	}


	/**
	 * @param array $arguments
	 *
	 * @return int
	 * @throws CommandException
	 */
	abstract public function run($arguments): int;


}
