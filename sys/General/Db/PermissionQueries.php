<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class PermissionQueries
 *
 * Base queries adjusted for permissions
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class PermissionQueries extends BaseQueries {

	/**
	 * @inheritDoc
	 */
	public static $tableName = 'permissions';


}
