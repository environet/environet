<?php

namespace Environet\Sys\General\Model\Configuration\Type\Parameters;

use Exception;
use SimpleXMLElement;

/**
 * Class AbstractFormatParameter
 *
 * Parameter model for format configuration paramter type.
 *
 * @package Environet\Sys\General\ConfigurationModels
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
abstract class AbstractFormatParameter {

	protected ?string $attribute = null;

	protected array $tagHierarchy = [];

	protected bool $optional = false;

	protected ?int $iterationStart = null;

	protected ?int $iterationEnd = null;


	/**
	 *
	 * @return static
	 * @throws Exception
	 */
	public static function fromConfig(array $config): AbstractFormatParameter {
		$type = $config['Parameter'] ?? null;
		$parameter = match ($type) {
			'MonitoringPoint' => new MonitoringPointParameter(),
			'ObservedPropertyValue' => new ObservedPropertyValueParameter(),
			'ObservedPropertySymbol' => new ObservedPropertySymbolParameter(),
			'DateTime', 'Date', 'Time', 'Year', 'Month', 'Day', 'Hour', 'Minute', 'Second' => new DateParameter(),
			default => throw new Exception('Invalid parameter type: ' . $type),
		};
		$parameter->setOptions($config);

		return $parameter;
	}


	/**
	 *
	 * @return $this
	 */
	public function setOptions(array $config): AbstractFormatParameter {
		if (isset($config['Attribute']) && !empty(trim((string) $config['Attribute']))) {
			$this->attribute = trim((string) $config['Attribute']);
		}

		//Set TagHierarchy
		if (isset($config['Tag Hierarchy'])) { //Convert it to the new format, without spaces
			$config['TagHierarchy'] = $config['Tag Hierarchy'];
		}
		if (isset($config['TagHierarchy']) && is_array($config['TagHierarchy'])) {
			$this->tagHierarchy = array_values(array_map('trim', $config['TagHierarchy']));
		}

		return $this;
	}


	public function getAttribute(): ?string {
		return $this->attribute;
	}


	public function getTagHierarchy(): array {
		return $this->tagHierarchy;
	}


	public function isOptional(): bool {
		return $this->optional;
	}


	public function getLastTag(): ?string {
		return $this->tagHierarchy[array_key_last($this->tagHierarchy)] ?? null;
	}


	/**
	 * Get the XPath of the parameter, optionally under a given path.
	 *
	 *
	 */
	public function getXPath(string $underPath = '', ?int $i = null): string {
		$underPath = explode('/', trim($underPath, '/'));
		$tagHierarchy = array_diff($this->tagHierarchy, $underPath);

		if ($this->isIterable()) {
			//If the parameter is iterable, replace the last tag's variable part with the iteration index
			$lastTagKey = array_key_last($tagHierarchy);
			$tagHierarchy[$lastTagKey] = preg_replace('/\[i:\d+-\d+\]$/', $i, $tagHierarchy[$lastTagKey]);
		}

		return implode('/', $tagHierarchy);
	}


	/**
	 * Get the value of the parameter from the given XML element. If the parameter is an attribute, the attribute value is returned, otherwise the element's value.
	 *
	 *
	 */
	public function getXmlValue(SimpleXMLElement $element, bool $skipEmptyValueTag = false): string {
		if ($this->getAttribute()) {
			return $element->attributes()[$this->getAttribute()]->__toString();
		} else {
			return $element->__toString();
		}
	}


	/**
	 * Check if the parameter is iterable, and set the iteration boundaries if it is.
	 * Iterable if the tag hierarchy ends with [i:start-end]
	 */
	public function isIterable(): bool {
		if ($this->iterationStart !== null && $this->iterationEnd !== null) {
			//Already checked and set
			return true;
		}
		if ($this->getLastTag() && preg_match('/^([^[]+)(\[i:(\d+)-(\d+)\])$/', $this->getLastTag(), $m)) {
			$this->iterationStart = (int) $m[3];
			$this->iterationEnd = (int) $m[4];

			return true;
		}

		return false;
	}


	/**
	 * Return the iteration boundaries if the parameter is iterable.
	 */
	public function getIterationBoundaries(): ?array {
		return $this->isIterable() ? [$this->iterationStart, $this->iterationEnd] : null;
	}


}
