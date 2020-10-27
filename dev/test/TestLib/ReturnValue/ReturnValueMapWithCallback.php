<?php

namespace TestLib\ReturnValue;

use PHPUnit\Framework\MockObject\Builder\InvocationStubber;
use PHPUnit\Framework\MockObject\Invocation;
use PHPUnit\Framework\MockObject\Stub\Stub;
use PHPUnit\Framework\MockObject\Stub\ReturnValueMap;

/**
 * Class ReturnValueMapWithCallback
 *
 * @package TestLib\ReturnValue
 * @author SRG Group <dev@srg.hu>
 * @copyright 2019 SRG Group Kft.
 * @method InvocationStubber method($constraint)
 */
class ReturnValueMapWithCallback implements Stub {

	/**
	 * @var array
	 */
	private array $valueMap;


	/**
	 * ReturnValueMapWithCallback constructor.
	 *
	 * @param array $valueMap
	 */
	public function __construct(array $valueMap) {
		$this->valueMap = $valueMap;
	}


	/**
	 * Support callbacks
	 *
	 * @param Invocation $invocation
	 * @return mixed
	 */
	public function invoke(Invocation $invocation) {
		$returnValueMap = new ReturnValueMap($this->valueMap);
		$value = $returnValueMap->invoke($invocation);

		if ($value instanceof \Closure) {
			$value = $value();
		}

		return $value;
	}


	/**
	 * @return string
	 */
	public function toString(): string {
		return 'return value from a map';
	}


}
