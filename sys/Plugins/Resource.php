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

	/** @var string Label to identify the data (e.g. a filename) */
	public $name;
	
	/** @var mixed Meta data associated with the resource */
	public $meta;
	
	/** @var string The actual data from the resource */
	public $contents;
}
