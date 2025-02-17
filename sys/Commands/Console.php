<?php


namespace Environet\Sys\Commands;

use Environet\Sys\Config;

/**
 * Class Console
 *
 * Wrapper class for console interactions (output and input)
 *
 * @package Environet\Sys\Commands
 * @author  SRG Group <dev@srg.hu>
 */
class Console {


	const COLOR_BLACK    = '0;30';
	const COLOR_RED      = '0;31';
	const COLOR_GREEN    = '0;32';
	const COLOR_YELLOW   = '0;33';
	const COLOR_WHITE    = '0;37';
	const BGCOLOR_BLACK  = '40';
	const BGCOLOR_RED    = '41';
	const BGCOLOR_GREEN  = '42';
	const BGCOLOR_YELLOW = '43';

	/**
	 * @var false|resource PHP standard input
	 */
	private $stdin;

	private static $instance;

	private \DateTimeZone $timezone;

	private string $timeFormat;

	protected bool $datePrefix = false;


	/**
	 * Console constructor.
	 * Create instance, and set standard input
	 */
	public function __construct() {
		self::$instance = $this;
		$this->stdin = fopen('php://stdin', 'r');
		$this->timezone = new \DateTimeZone(Config::getInstance()->getTimezone() ?? 'UTC');
		$this->timeFormat = DATE_ATOM;
	}


	/**
	 * @return Console
	 */
	public static function getInstance(): Console {
		return self::$instance;
	}


	/**
	 * Write to output without a line break
	 *
	 * @param string      $string
	 * @param string|null $color
	 * @param string|null $bgColor
	 * @param bool        $outError
	 * @param bool        $outBoth
	 * @param bool        $datePrefix
	 */
	public function write(
		string $string,
		string $color = null,
		string $bgColor = null,
		bool $outError = false,
		bool $outBoth = false,
		?bool $datePrefix = null
	) {
		if ($datePrefix ?? $this->datePrefix) {
			// Add date prefix
			$dt = new \DateTime();
			$dt->setTimezone($this->timezone);
			$datePrefix = $dt->format($this->timeFormat) . ' ';
		}

		$colorPrefix = $this->buildColorPrefix($color, $bgColor);
		$colorPostfix = $colorPrefix ? "\e[0m" : "";

		if ($outError || $outBoth) {
			// Write to stderr
			fwrite(STDERR, $datePrefix . $colorPrefix . $string . $colorPostfix);
		}
		if (!$outError || $outBoth) {
			// Write to stdout
			echo $datePrefix . $colorPrefix . $string . $colorPostfix;
		}
	}


	/**
	 * Write a linebreak
	 */
	public function writeLineBreak() {
		echo "\n";
	}


	/**
	 * Write to output with a line break
	 *
	 * @param string      $string
	 * @param string|null $color
	 * @param string|null $bgColor
	 * @param bool        $outError
	 * @param bool        $outBoth
	 * @param bool|null   $datePrefix
	 */
	public function writeLine(
		string $string,
		string $color = null,
		string $bgColor = null,
		bool $outError = false,
		bool $outBoth = false,
		?bool $datePrefix = null
	) {
		$this->write($string, $color, $bgColor, $outError, $outBoth, $datePrefix);
		$this->writeLineBreak();
	}


	/**
	 * Write with date prefix
	 *
	 * @param string      $string
	 * @param string|null $color
	 * @param string|null $bgColor
	 * @param bool        $outError
	 * @param bool        $outBoth
	 */
	public function writeLineDp(string $string, string $color = null, string $bgColor = null, bool $outError = false, bool $outBoth = false) {
		$this->write($string, $color, $bgColor, $outError, $outBoth, true);
		$this->writeLineBreak();
	}


	/**
	 * Ask for an answer.
	 *
	 * @param string $string
	 *
	 * @return mixed
	 */
	public function ask(string $string) {
		$this->writeLine($string);
		$this->write("> ");

		$value = trim(fgets($this->stdin));
		$this->writeLineBreak();

		return $value;
	}


	/**
	 * Ask for an answer, with default value.
	 *
	 * @param string $string
	 * @param mixed  $defaultValue
	 * @param int    $length
	 *
	 * @return mixed
	 */
	public function askWithDefault(string $string, $defaultValue) {
		$string = "$string [default: $defaultValue]";
		$this->writeLine($string);
		$this->write("> ");

		$value = trim(fgets($this->stdin));
		if ($value === '') {
			//Return default if empty answer was given
			$value = $defaultValue;
		}
		$this->writeLineBreak();

		return $value;
	}


	/**
	 * Ask for an answer, and hide answer (for password prompts)
	 *
	 * @param string $string
	 * @param int    $length
	 *
	 * @return mixed
	 */
	public function askHidden(string $string) {
		$this->writeLine($string);
		$this->write("> ");

		system('stty -echo');
		$value = trim(fgets($this->stdin));
		system('stty echo');

		$this->writeLineBreak();

		return $value;
	}


	/**
	 * Ask for a numeric option and get an answer
	 *
	 * @param string $string
	 *
	 * @return int
	 */
	public function askOption(string $string = "Your choice:"): int {
		while (true) {
			$option = $this->ask($string);
			if (!ctype_digit($option)) {
				$this->writeLine("Enter a number!");
				continue;
			}
			break;
		}

		return $option;
	}


	/**
	 * Ask for a numeric option with options array and get an answer
	 *
	 * @param string $question
	 * @param array  $options
	 *
	 * @return int
	 */
	public function askOptions(string $question, array $options): int {
		$this->writeLine($question);
		foreach ($options as $key => $option) {
			$this->writeLine("$key: $option");
		}
		while (true) {
			$option = $this->ask("Your choice:");
			if (!ctype_digit($option)) {
				$this->writeLine("Enter a number!");
				continue;
			}
			if (!isset($options[$option])) {
				$this->writeLine("Invalid option!");
				continue;
			}
			break;
		}

		return $option;
	}


	/**
	 * Ask a yes-no question with default answer
	 *
	 * @param string $string
	 * @param bool   $default
	 *
	 * @return bool
	 */
	public function askYesNo(string $string, bool $default = true): bool {
		$answer = $this->ask($string . ' ' . ($default ? '(Y/n)' : '(y/N)'));
		if ($answer === '') {
			return $default;
		}

		return (strtolower($answer) == 'y');
	}


	/**
	 * Turn on date prefix
	 *
	 * @return $this
	 */
	public function setDatePrefix(): Console {
		$this->datePrefix = true;

		return $this;
	}


	/**
	 * Turn off date prefix
	 *
	 * @return $this
	 */
	public function resetDatePrefix(): Console {
		$this->datePrefix = false;

		return $this;
	}


	/**
	 * Get a color (and/or background color) format to display in the console.
	 *
	 * @param string|null $color
	 * @param string|null $bgColor
	 *
	 * @return string
	 */
	protected function buildColorPrefix(string $color = null, string $bgColor = null): string {
		if (!$color && !$bgColor) {
			return '';
		}
		$parts = [];
		if ($color) {
			$parts[] = $color;
		}
		if ($bgColor) {
			$parts[] = $bgColor;
		}

		return "\e[" . implode(';', $parts) . "m";
	}


}
