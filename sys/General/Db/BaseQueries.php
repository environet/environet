<?php

namespace Environet\Sys\General\Db;

use Environet\Sys\General\Db\Query\Delete;
use Environet\Sys\General\Db\Query\Insert;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\Query\Update;
use Environet\Sys\General\EventLogger;
use Environet\Sys\General\Exceptions\MissingEventTypeException;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class BaseQueries
 *
 * @package Environet\Sys\General\Db
 * @author  SRG Group <dev@srg.hu>
 */
class BaseQueries {

	/**
	 * Table name of the current page.
	 * @var string
	 */
	public static $tableName;

	/**
	 * List of the searchable fields in the $tableName table.
	 * @var array
	 */
	public static $searchableFields = [];


	/**
	 * Save values in a connection table.
	 * It needs an array of ids, and some configuration attributes. Deletes old connection values, and re-creates every connection.
	 *
	 * @param array  $values          Array of ids
	 * @param string $connectionTable The connection table to fill with values
	 * @param string $colLeft         Name of the column to where the ids from $values will be saved
	 * @param string $colRight        Name of the column to where the $idRight saved
	 * @param int    $idRight         A single id for the right side of the connection
	 * @param bool   $truncate        If we have to truncate the previous data, then it has to be true
	 *
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Delete::run()
	 * @uses \Environet\Sys\General\Db\Query\Insert::run()
	 */
	public static function saveConnections($values, string $connectionTable, string $colLeft, string $colRight, int $idRight, bool $truncate = false) {
		// Get user ids from posted data, filter it, and filter out duplicates
		$ids = array_unique(array_filter($values ?? []));
		if (!empty($ids)) {
			if ($truncate) {
				// Delete all connection first, all current connection will be re-created
				(new Delete())->table($connectionTable)->where($colRight . ' = :' . $colRight)->addParameter(':' . $colRight, $idRight)->run();
			}

			// Create insert query for new connections
			$insert = (new Insert())->table($connectionTable)->columns([$colLeft, $colRight]);

			$insert->addParameter(':rightId', $idRight);
			// Add values for all ids
			foreach ($ids as $key => $id) {
				$insert->addValueRow([":leftId$key", ":rightId"]);
				$insert->addParameters([
					":leftId$key" => (int) $id,
				]);
			}

			// Run, but without returning last insert id
			$insert->run(Query::RETURN_BOOL);
		}
	}


	/**
	 * Get record by id
	 *
	 * @param mixed  $id         Item's identifier to query it.
	 * @param string $primaryKey The primary key of the specified table.
	 *
	 * @return array|null
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 */
	public static function getById($id, string $primaryKey = 'id'): ?array {
		try {
			return (new Select())
				->select(static::$tableName . '.*')
				->from(static::$tableName)
				->where(static::$tableName . '.' . $primaryKey . ' = :id')
				->addParameter(':id', $id)
				->run(Query::FETCH_FIRST);
		} catch (QueryException $exception) {
			return null;
		}
	}


	/**
	 * Update or insert an item to specified table.
	 * Logs the transaction regardless of updating or inserting.
	 *
	 * @param array  $data       Data to save.
	 * @param mixed  $id         If the id is exist, update otherwise insert the new record.
	 * @param string $primaryKey The primary key of the specified table.
	 *
	 * @throws MissingEventTypeException
	 * @throws QueryException
	 * @throws \Environet\Sys\General\Exceptions\InvalidConfigurationException
	 * @uses \Environet\Sys\General\Db\BaseQueries::prepareData()
	 * @uses \Environet\Sys\General\Db\BaseQueries::getUpdateEventType()
	 * @uses \Environet\Sys\General\Db\BaseQueries::getInsertEventType()
	 * @uses \Environet\Sys\General\Db\Query\Insert::run()
	 * @uses \Environet\Sys\General\Db\Query\Update::run()
	 * @uses \Environet\Sys\General\EventLogger::log()
	 * @see  Connection
	 */
	public static function save(array $data, $id = null, string $primaryKey = 'id') {
		$connection = Connection::getInstance();
		$connection->pdo->beginTransaction();

		$dataToSave = static::prepareData($data);

		if ($id) {
			EventLogger::log(static::getUpdateEventType(), array_merge($dataToSave, [
				$primaryKey => $id
			]));

			(new Update())
				->table(static::$tableName)
				->updateData($dataToSave)
				->where(static::$tableName . '.' . $primaryKey . ' = :id')
				->addParameter(':id', $id)
				->run(Query::RETURN_BOOL);
		} else {
			(new Insert())
				->table(static::$tableName)
				->addSingleData($dataToSave)
				->run(Query::RETURN_BOOL);

			EventLogger::log(static::getInsertEventType(), $dataToSave);
		}

		$connection->pdo->commit();
	}


	/**
	 * Delete an item.
	 * Logs the event.
	 *
	 * @param int    $id         Item's identifier to delete.
	 * @param bool   $soft       Possible to soft delete an item.
	 * @param string $primaryKey The primary key of the specified table.
	 *
	 * @throws MissingEventTypeException
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\BaseQueries::getDeleteEventType()
	 * @uses \Environet\Sys\General\Db\Query\Delete::run()
	 */
	public static function delete(int $id, bool $soft = false, string $primaryKey = 'id') {
		EventLogger::log(static::getDeleteEventType(), ['id' => $id]);

		if ($soft) {
			(new Update())
				->table(static::$tableName)
				->where(static::$tableName . '.' . $primaryKey . ' = :id')
				->addSet('deleted_at', ':deletedAt')
				->setParameters([
					':id'        => $id,
					':deletedAt' => date('Y-m-d H:i:s')
				])
				->run();
		} else {
			(new Delete())->table(static::$tableName)->where($primaryKey . ' = :id')->addParameter(':id', $id)->run();
		}
	}


	/**
	 * Get a simple list of records. Keys are the id, label is configurable
	 *
	 * @param string $labelField The name field of the table.
	 * @param string $primaryKey The primary key of the specified table.
	 *
	 * @return array|null
	 * @uses \Environet\Sys\General\Db\Query\Select::run()
	 * @uses \exception_logger()
	 */
	public static function getOptionList(string $labelField = 'name', string $primaryKey = 'id'): ?array {
		try {
			$records = (new Select())
				->from(static::$tableName)
				->select([static::$tableName . '.' . $primaryKey, static::$tableName . '.' . $labelField])
				->orderBy(static::$tableName . '.' . $primaryKey, 'ASC')
				->run();
			$records = array_combine(array_column($records, $primaryKey), array_column($records, $labelField));

			return $records ?: [];
		} catch (QueryException $e) {
			exception_logger($e);

			return [];
		}
	}


	/**
	 * Preparing data before saving it.
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public static function prepareData(array $data): array {
		return [];
	}


	/**
	 * If any part of the code use the base save method,
	 * we have to log also the update event with specified type.
	 *
	 * @throws MissingEventTypeException
	 */
	public static function getUpdateEventType(): string {
		throw new MissingEventTypeException('Missing update event type from queries class!');
	}


	/**
	 * If any part of the code use the base save method,
	 * we have to log also the insert event with specified type.
	 *
	 * @throws MissingEventTypeException
	 */
	public static function getInsertEventType(): string {
		throw new MissingEventTypeException('Missing insert event type from queries class!');
	}


	/**
	 * If any part of the code use the base save method,
	 * we have to log also the insert event with specified type.
	 *
	 * @throws MissingEventTypeException
	 */
	public static function getDeleteEventType(): string {
		throw new MissingEventTypeException('Missing delete event type from queries class!');
	}


}
