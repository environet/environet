<?php

namespace Environet\Sys\General\Model;

use Environet\Sys\General\Model\Configuration\Type\Parameters\DateParameter;
use Environet\Sys\General\Model\Configuration\Type\Parameters\MonitoringPointParameter;
use Environet\Sys\General\Model\Configuration\Type\Parameters\ObservedPropertySymbolParameter;

/**
 * Class ResolvedGroup
 *
 * Model class for resolved group in XmlParser. It contains a list of resolved items, which are the resolved parameters and values.
 *
 * @package Environet\Sys\General\Model
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class ResolvedGroup {

	protected array $items;


	/**
	 * @return array<ResolvedItem>
	 */
	public function getItems(): array {
		return $this->items;
	}


	/**
	 * Filter items by parameter class
	 *
	 * @param string $parameterClass
	 *
	 * @return array<ResolvedItem>
	 */
	public function getItemsWithParameter(string $parameterClass): array {
		return array_values(array_filter($this->getItems(), fn(ResolvedItem $item) => $item->getParameter() instanceof $parameterClass));
	}


	/**
	 * Add an item to the group
	 *
	 * @param ResolvedItem $item
	 *
	 * @return ResolvedGroup
	 */
	public function addItem(ResolvedItem $item): ResolvedGroup {
		$this->items[] = $item;

		return $this;
	}


	/**
	 * Remove an item from the group
	 *
	 * @param ResolvedItem $item
	 *
	 * @return void
	 */
	public function removeItem(ResolvedItem $item) {
		$this->items = array_values(array_filter($this->items, fn(ResolvedItem $i) => $i !== $item));
	}


	/**
	 * Get the only one monitoring point item from the group (multiple item with this parameter not expected, but if it is, the first one is returned)
	 * @return ResolvedItem|null
	 */
	public function getMonitoringPointItem(): ?ResolvedItem {
		return array_values(array_filter($this->items, fn(ResolvedItem $item) => $item->getParameter() instanceof MonitoringPointParameter))[0] ?? null;
	}


	/**
	 * Get the only one observed property symbol item from the group (multiple item with this parameter not expected, but if it is, the first one is returned)
	 * @return ResolvedItem|null
	 */
	public function getObservedPropertySymbolItem(): ?ResolvedItem {
		return array_values(array_filter($this->items, fn(ResolvedItem $item) => $item->getParameter() instanceof ObservedPropertySymbolParameter))[0] ?? null;
	}


	/**
	 * Get the date parameter items from the group. It can be multiple if dates are stored in different parameters.
	 * @return array<ResolvedItem>
	 */
	public function getDateItems(): array {
		return array_values(array_filter($this->items, fn(ResolvedItem $item) => $item->getParameter() instanceof DateParameter));
	}


	/**
	 * Clone the items in the group
	 * @return void
	 */
	public function __clone() {
		$this->items = array_map(fn(ResolvedItem $item) => new ResolvedItem($item->getParameter(), $item->getValue()), $this->items);
	}


}
