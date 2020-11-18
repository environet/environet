<?php

namespace TestLib\Cases;

use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;

/**
 * Class TestCase
 *
 * @package   TestLib
 * @author    SRG Group <dev@srg.hu>
 * @copyright 2020 SRG Group Kft.
 */
class TestCase extends \PHPUnit\Framework\TestCase {

	/**
	 * @var string
	 */
	protected $testFolderPath = DATA_PATH . '/__test';


	/**
	 * Build chainMethods
	 *
	 * @param MockObject $mock
	 * @param array      $methods
	 */
	public function chainMethods(MockObject $mock, array $methods = []) {
		foreach ($methods as $method) {
			$mock->method($method)->willReturn($mock);
		}
	}


	/**
	 * Access private properties
	 *
	 * @param $className
	 * @param $propertyName
	 *
	 * @return \ReflectionProperty
	 * @throws \ReflectionException
	 */
	public function getPrivateProperty($className, $propertyName) {
		$reflector = new ReflectionClass($className);
		$property = $reflector->getProperty($propertyName);
		$property->setAccessible(true);

		return $property;
	}


	/**
	 * @param      $fullPath
	 * @param null $contents
	 */
	public function createFileWithDir($fullPath, $contents = null) {
		$dirPath = dirname($fullPath);
		if (!is_dir($dirPath)) {
			mkdir($dirPath, 0755, true);
		}

		file_put_contents($fullPath, $contents);
	}


	/**
	 * Create test folder under data folder
	 */
	public function createTestFolder() {
		if (!is_dir($this->testFolderPath)) {
			mkdir($this->testFolderPath, 0755);
		}
	}


	/**
	 * @param      $fileSubPath
	 * @param null $contents
	 */
	public function createFileWithDirInTestFolder($fileSubPath, $contents = null) {
		$separator = substr($fileSubPath, 0, 1) === '/' ? '' : '/';
		$fullPath = $this->testFolderPath . $separator . $fileSubPath;
		$this->createFileWithDir($fullPath, $contents);
	}


	/**
	 * Clean test folder
	 */
	public function cleanTestFolder() {
		rrmdir($this->testFolderPath);
	}


	/**
	 * @param string $method
	 * @param object $object
	 * @param array  $args
	 *
	 * @return mixed
	 * @throws \ReflectionException
	 */
	public function callProtectedMethod(string $method, object $object, array $args = []) {
		$class = new ReflectionClass(get_class($object));
		$method = $class->getMethod($method);
		$method->setAccessible(true);
		if (!empty($args)) {
			return $method->invokeArgs($object, $args);
		} else {
			return $method->invoke($object);
		}
	}


}
