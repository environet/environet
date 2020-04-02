<?php


namespace Environet\Sys\Plugins\Transports;

/**
 * Class Resource
 *
 * Data object, to allow labeling of the source of data from various transports
 *
 * @package Environet\Sys\Plugins\Transports
 */
class Resource {

	/** @var string Label to identify the data (e.g. a filename)*/
	public $name;

	public $contents;
}
