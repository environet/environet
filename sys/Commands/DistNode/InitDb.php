<?php


namespace Environet\Sys\Commands\DistNode;

use Environet\Sys\Commands\Console;
use Environet\Sys\Commands\Exceptions\CommandException;
use Environet\Sys\General\Db\Query\Insert;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class InitDb
 *
 * Initializes a clean database copy for a new distribution node
 *
 * @package Environet\Sys\Commands\DistNode
 * @author  SRG Group <dev@srg.hu>
 */
class InitDb extends DbCommand {


	/**
	 * Run the initialization command.
	 *
	 * The command does the following steps:
	 * 1. Imports the database schema via {@see InitDb::importSchema()}.
	 * 2. Imports a copy of clean starting data via {@see InitDb::importData()}.
	 * 3. Prompts the user for an administrator username and password to use in the future. The password has to be confirmed.
	 * 4. Creates the administrator account with the provided data.
	 *
	 * @param array $arguments
	 * @param array $options
	 *
	 * @return int
	 * @throws CommandException
	 * @uses \Environet\Sys\Commands\DistNode\InitDb::importSchema()
	 * @uses \Environet\Sys\Commands\DistNode\InitDb::importData()
	 */
	public function run($arguments, $options): int {
		$output = [];
		// Import structure
		$exitCode = $this->importSchema($output);
		if ($exitCode > 0) {
			echo implode("\n", $output);

			return $exitCode;
		}
		$this->console->writeLine('Database structure successfully imported', Console::COLOR_GREEN);

		// Structure is imported, so import clean data
		$exitCode = $this->importData($output);
		if ($exitCode > 0) {
			echo implode("\n", $output);

			return $exitCode;
		}
		$this->console->writeLine('Database content successfully imported', Console::COLOR_GREEN);

		(new MigrateDb($this->console))->run([]);

		/**
		 * Ask for admin user parameters, and create this user
		 */
		while (true) {
			// Username
			$adminUser = $this->console->ask("Enter the default administrator's username:");
			if (!$adminUser) {
				$this->console->writeLine("Admin username is invalid!", Console::COLOR_RED);
				continue;
			}
			break;
		}

		while (true) {
			// E-mail address
			$adminEmail = $this->console->ask("Enter the default administrator's email address:");
			if (!$adminEmail) {
				$this->console->writeLine("Admin email address is invalid!", Console::COLOR_RED);
				continue;
			}
			break;
		}

		passwordPrompt:
		while (true) {
			// Admin password
			$adminPass1 = $this->console->askHidden("Enter the password of $adminUser:");
			if (!$adminPass1) {
				$this->console->writeLine("Admin password is invalid!", Console::COLOR_RED);
				continue;
			}
			break;
		}

		while (true) {
			// Confirmation password
			$adminPass2 = $this->console->askHidden("Confirm the password of $adminUser:");
			if (!$adminPass2) {
				$this->console->writeLine("Admin password is invalid!", Console::COLOR_RED);
				continue;
			}
			break;
		}

		if ($adminPass1 !== $adminPass2) {
			// Password and confirmation is not the same, jump to password-part of the prompts
			$this->console->writeLine("Password and confirmation is not the same! Try again!", Console::COLOR_RED);
			goto passwordPrompt;
		}

		try {
			// Insert user to database
			$id = (new Insert())->table('users')->addSingleData([
				'username' => $adminUser,
				'password' => password_hash($adminPass1, PASSWORD_DEFAULT),
				'email'    => $adminEmail
			])->run();
			if (!$id) {
				throw new QueryException("Insert user failed");
			}
			// If user is created, attach the admin permission to it
			(new Insert())->table('user_permissions')->addSingleData([
				'permissionsid' => 1,
				'usersid'       => $id
			])->run();
			$this->console->writeLine("User successfully added with ID #" . $id, Console::COLOR_GREEN);
		} catch (QueryException $e) {
			$this->console->writeLine("Error while trying to save admin user: " . $e->getMessage(), Console::COLOR_RED);

			return 1;
		}

		return $exitCode;
	}


	/**
	 * Import db structure from an SQL file
	 *
	 * Checks if the schema sql file is present and forwards it to {@see InitDb::runSqlFile()} for the actual import process.
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws CommandException
	 * @uses \Environet\Sys\Commands\DistNode\InitDb::runSqlFile()
	 */
	protected function importSchema(array &$output): int {
		$schemaPath = SRC_PATH . '/database/schema.sql';

		if (!file_exists($schemaPath)) {
			throw new CommandException('Can\'t find default schema file');
		}

		return $this->runSqlFile($schemaPath, $output);
	}


	/**
	 * Import clean data based on an SQL file
	 *
	 * Checks if the sql file containing the clean data is present and forwards it to {@see InitDb::runSqlFile()} for the actual import process.
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws CommandException
	 * @uses \Environet\Sys\Commands\DistNode\InitDb::runSqlFile()
	 */
	protected function importData(array &$output): int {
		$dataP1th = SRC_PATH . '/database/clean_data.sql';

		if (!file_exists($dataP1th)) {
			throw new CommandException('Can\'t find clean data file');
		}

		return $this->runSqlFile($dataP1th, $output);
	}


}
