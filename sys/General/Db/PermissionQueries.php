<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class PermissionQueries
 *
 * @package   Environet\Sys\General\Db
 * @author    SRG Group <dev@srg.hu>
 * @copyright 2020 SRG Group Kft.
 */
class PermissionQueries extends BaseQueries {

	/**
	 * @inheritDoc
	 */
	public static $tableName = 'permissions';


}
