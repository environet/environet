<?php


namespace Environet\Sys\Plugins;

/**
 * Class Resource
 *
 * Data object, to allow labeling of the source of data from various transports
 *
 * @package Environet\Sys\Plugins\Transports
 * @author  SRG Group <dev@srg.hu>
 */
class Resource {

	/** @var string|null Label to identify the data (e.g. a filename) */
	protected ?string $url = null;

	/**
	 * @var string|null The subfile of the resource (e.g. the name of the file in the zip)
	 */
	protected ?string $subFile = null;

	/** @var string|null Label to identify the data (e.g. a filename) */
	protected ?string $name = null;

	/** @var string|null The actual data from the resource */
	protected ?string $contents = null;

	/**
	 * @var string|null The NCD of the monitoring point associated with the resource
	 */
	protected ?string $specificPointNCD = null;

	/**
	 * @var array<string> The NCDs of the monitoring points associated with the resource
	 */
	protected array $pointNCDs = [];

	/**
	 * @var string|null The symbol of the specific property associated with the resource
	 */
	protected ?string $specificPropertySymbol = null;

	/**
	 * @var array<string> The symbols of the properties associated with the resource
	 */
	protected array $propertySymbols = [];

	/**
	 * @var array<array> The observed property conversions
	 */
	protected array $observedPropertyConversions = [];

	/**
	 * @var bool Whether to keep extra data
	 */
	protected bool $keepExtraData = false;

	/**
	 * @var string|null File path to the local copy of the resource
	 */
	protected ?string $localCopyPath = null;


	/**
	 * @return string|null
	 */
	public function getUrl(): ?string {
		return $this->url;
	}


	/**
	 * @param string|null $url
	 *
	 * @return Resource
	 */
	public function setUrl(?string $url): Resource {
		$this->url = $url;

		return $this;
	}


	/**
	 * @return string|null
	 */
	public function getSubFile(): ?string {
		return $this->subFile;
	}


	/**
	 * @param string|null $subFile
	 *
	 * @return Resource
	 */
	public function setSubFile(?string $subFile): Resource {
		$this->subFile = $subFile;

		return $this;
	}


	/**
	 * @return string|null
	 */
	public function getName(): ?string {
		return $this->name;
	}


	/**
	 * @param string|null $name
	 *
	 * @return Resource
	 */
	public function setName(?string $name): Resource {
		$this->name = $name;

		return $this;
	}


	/**
	 * @return string|null
	 */
	public function getContents(): ?string {
		return $this->contents;
	}


	/**
	 * @param string|null $contents
	 *
	 * @return Resource
	 */
	public function setContents(?string $contents): Resource {
		$this->contents = $contents;

		return $this;
	}


	/**
	 * @return string|null
	 */
	public function getSpecificPointNCD(): ?string {
		return $this->specificPointNCD;
	}


	/**
	 * @param string|null $specificPointNCD
	 *
	 * @return Resource
	 */
	public function setSpecificPointNCD(?string $specificPointNCD): Resource {
		$this->specificPointNCD = $specificPointNCD;

		return $this;
	}


	/**
	 * @return array
	 */
	public function getPointNCDs(): array {
		return $this->pointNCDs;
	}


	/**
	 * @param array $pointNCDs
	 *
	 * @return Resource
	 */
	public function setPointNCDs(array $pointNCDs): Resource {
		$this->pointNCDs = $pointNCDs;

		return $this;
	}


	/**
	 * @return string|null
	 */
	public function getSpecificPropertySymbol(): ?string {
		return $this->specificPropertySymbol;
	}


	/**
	 * @param string|null $specificPropertySymbol
	 *
	 * @return Resource
	 */
	public function setSpecificPropertySymbol(?string $specificPropertySymbol): Resource {
		$this->specificPropertySymbol = $specificPropertySymbol;

		return $this;
	}


	/**
	 * @return array
	 */
	public function getPropertySymbols(): array {
		return $this->propertySymbols;
	}


	/**
	 * @param array $propertySymbols
	 *
	 * @return Resource
	 */
	public function setPropertySymbols(array $propertySymbols): Resource {
		$this->propertySymbols = $propertySymbols;

		return $this;
	}


	/**
	 * @return array
	 */
	public function getObservedPropertyConversions(): array {
		return $this->observedPropertyConversions;
	}


	/**
	 * @param array $observedPropertyConversions
	 *
	 * @return Resource
	 */
	public function setObservedPropertyConversions(array $observedPropertyConversions): Resource {
		$this->observedPropertyConversions = $observedPropertyConversions;

		return $this;
	}


	/**
	 * @return bool
	 */
	public function isKeepExtraData(): bool {
		return $this->keepExtraData;
	}


	/**
	 * @param bool $keepExtraData
	 *
	 * @return Resource
	 */
	public function setKeepExtraData(bool $keepExtraData): Resource {
		$this->keepExtraData = $keepExtraData;

		return $this;
	}


	/**
	 * @return string|null
	 */
	public function getLocalCopyPath(): ?string {
		return $this->localCopyPath;
	}


	/**
	 * @param string|null $localCopyPath
	 *
	 * @return Resource
	 */
	public function setLocalCopyPath(?string $localCopyPath): Resource {
		$this->localCopyPath = $localCopyPath;

		return $this;
	}


	/**
	 * Support fir backward compatibility
	 *
	 * @param $name
	 *
	 * @return array|void
	 */
	public function __get($name) {
		if (property_exists($this, $name)) {
			return $this->$name;
		}
		if ($name === 'meta') {
			return [
				'MonitoringPointNCDs'         => $this->specificPointNCD ? [$this->specificPointNCD] : $this->pointNCDs,
				'ObservedPropertySymbols'     => $this->specificPropertySymbol ? [$this->specificPropertySymbol] : $this->propertySymbols,
				'observedPropertyConversions' => $this->observedPropertyConversions,
			];
		}
	}


	/**
	 * Support fir backward compatibility
	 *
	 * @param $name
	 * @param $value
	 *
	 * @return void
	 */
	public function __set($name, $value) {
		if (property_exists($this, $name)) {
			$this->$name = $value;
		}
	}


}
