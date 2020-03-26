<?php


namespace Environet\Sys\Commands\DistNode;

use Environet\Sys\Commands\BaseCommand;
use Environet\Sys\Commands\Console;
use Environet\Sys\Commands\Exceptions\CommandException;
use Environet\Sys\Config;
use Environet\Sys\General\Db\Connection;
use Environet\Sys\General\Db\Query\Insert;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class InitDb
 *
 * @package Environet\Sys\Commands\DataNode
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class InitDb extends BaseCommand {


	/**
	 * @inheritDoc
	 */
	public function run($arguments): int {
		$output = [];
		//Import structure
		$exitCode = $this->importSchema($output);
		if ($exitCode > 0) {
			echo implode("\n", $output);
			return $exitCode;
		}
		$this->console->writeLine('Database structure successfully imported', Console::COLOR_GREEN);

		//Structure is imported, so import clean data
		$exitCode = $this->importData($output);
		if ($exitCode > 0) {
			echo implode("\n", $output);
			return $exitCode;
		}
		$this->console->writeLine('Database content successfully imported', Console::COLOR_GREEN);

		//Schema and clean data is imported, we can make a connection
		$connection = Connection::getInstance();

		/**
		 * Ask for admin user parameters, and create this user
		 */
		while (true) {
			//Username
			$adminUser = $this->console->ask("Enter the default administrator's username:");
			if (!$adminUser) {
				$this->console->writeLine("Admin username is invalid!", Console::COLOR_RED);
				continue;
			}
			break;
		}

		while (true) {
			//E-mail address
			$adminEmail = $this->console->ask("Enter the default administrator's email address:");
			if (!$adminEmail) {
				$this->console->writeLine("Admin email address is invalid!", Console::COLOR_RED);
				continue;
			}
			break;
		}

		passwordPrompt:
		while (true) {
			//Admin password
			$adminPass1 = $this->console->askHidden("Enter the password of $adminUser:");
			if (!$adminPass1) {
				$this->console->writeLine("Admin password is invalid!", Console::COLOR_RED);
				continue;
			}
			break;
		}

		while (true) {
			//Confirmation password
			$adminPass2 = $this->console->askHidden("Confirm the password of $adminUser:");
			if (!$adminPass2) {
				$this->console->writeLine("Admin password is invalid!", Console::COLOR_RED);
				continue;
			}
			break;
		}

		if ($adminPass1 !== $adminPass2) {
			//Password and confirmation is not the same, jumpt to password-part of the prompts
			$this->console->writeLine("Password and confirmation is not the same! Try again!", Console::COLOR_RED);
			goto passwordPrompt;
		}

		try {
			//Insert user to database
			$id = (new Insert())->table('users')->addSingleData([
				'username' => $adminUser,
				'password' => password_hash($adminPass1, PASSWORD_DEFAULT),
				'email'    => $adminEmail
			])->run();
			if (!$id) {
				throw new QueryException("Insert user failed");
			}
			//If user is created, attach the admin permission to it
			(new Insert())->table('user_permissions')->addSingleData([
				'permissionsid' => 1,
				'usersid' => $id
			])->run();
			$this->console->writeLine("User successfully added with ID #".$id, Console::COLOR_GREEN);
		} catch (QueryException $e) {
			$this->console->writeLine("Error while trying to save admin user: ".$e->getMessage(), Console::COLOR_RED);
			return 1;
		}

		return $exitCode;
	}


	/**
	 * Import db structure from an SQL file
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws CommandException
	 */
	protected function importSchema(array &$output): int {
		$schemaPath = SRC_PATH.'/database/schema.sql';

		if (!file_exists($schemaPath)) {
			throw new CommandException('Can\'t find default schema file');
		}

		return $this->runSqlFile($schemaPath, $output);
	}


	/**
	 * Import clean data based on an SQL file
	 *
	 * @param array $output
	 *
	 * @return int
	 * @throws CommandException
	 */
	protected function importData(array &$output): int {
		$dataP1th = SRC_PATH.'/database/clean_data.sql';

		if (!file_exists($dataP1th)) {
			throw new CommandException('Can\'t find clean data file');
		}

		return $this->runSqlFile($dataP1th, $output);
	}


	/**
	 * Run the content of an SQL file with psql CLI command
	 *
	 * @param string $file SQL file
	 * @param array  $output Collect the output in this array
	 *
	 * @return int
	 */
	protected function runSqlFile(string $file, array &$output): int {
		//Get database parameters
		$config = Config::getInstance();
		$host = $config->getDatabaseHost();
		$user = $config->getDatabaseUser();
		$pass = $config->getDatabasePass();
		$db = $config->getDatabaseDatabase();
		$port = $config->getDatabasePort();

		$exitCode = 0;
		//psql command will ask for password if we don't store it on a .pgpass file, so create this file temporarily
		$passFile = '~/.pgpass';
		exec("touch $passFile && chmod 600 $passFile &&  echo \"$host:$port:$db:$user:$pass\" > $passFile", $output, $exitCode);

		if ($exitCode === 0) {
			//Run psql import command
			exec("psql --host=$host --username=$user --dbname=$db --port=$port < $file 2>&1", $output, $exitCode);
		}

		//Remove pgpass file
		exec("rm $passFile");

		return $exitCode;
	}


}
