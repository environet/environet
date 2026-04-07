<?php


namespace Environet\Sys\Commands;

use Environet\Sys\Commands\Exceptions\CommandException;

/**
 * Class BaseCommand
 *
 * Base class for console commands.
 *
 * @package Environet\Sys\Commands
 * @author  SRG Group <dev@srg.hu>
 */
abstract class BaseCommand {

	/**
	 * @var Console
	 */
	protected $console;


	/**
	 * BaseCommand constructor.
	 *
	 */
	public function __construct(Console $console) {
		$this->console = $console;
	}


	/**
	 * Method stub to run commands with.
	 *
	 * @param array $arguments
	 * @param array $options
	 *
	 * @throws CommandException
	 */
	abstract public function run($arguments, $options): int;


}
