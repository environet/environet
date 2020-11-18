<?php


namespace Environet\Sys\Admin\Pages\MonitoringPoint;

/**
 * Interface MonitoringPointInterface
 *
 * Interface for classes able to map CSV files into monitoring point data.
 *
 * @package Environet\Sys\Admin\Pages\MonitoringPoint
 * @author  SRG Group <dev@srg.hu>
 */
interface MonitoringPointCSVMapInterface {


	/**
	 * Get the name of the related observed properties query class.
	 *
	 * @return string
	 */
	public function getObservedPropertyQueriesClass(): string;


	/**
	 * Get the international identification key's name.
	 *
	 * @return string
	 */
	public function getGlobalIdName(): string;


}
