<?php

namespace Environet\Sys\Admin\Pages\MonitoringPoint;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\OperatorQueries;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\UserQueries;
use Environet\Sys\General\Exceptions\QueryException;

/**
 * Class MonitoringPointCrud
 *
 * Base class for handling CRUD operations for monitoring points.
 *
 * @package Environet\Sys\Admin\Pages\MonitoringPoint
 * @author  SRG Group <dev@srg.hu>
 */
abstract class MonitoringPointCrud extends CrudPage implements MonitoringPointCSVMapInterface {

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'Monitoring point successfully saved';

	/**
	 * Heading line of input CSV
	 *
	 * @var string[]
	 */
	private $headingLine;

	/**
	 * @var string
	 */
	protected $readOwnPermissionName;

	/**
	 * @var string
	 */
	protected $createOwnPermissionName;

	/**
	 * @var string
	 */
	protected $updateOwnPermissionName;


	/**
	 * @param bool $plural
	 *
	 * @return string
	 */
	protected function getEntityName(bool $plural = false): string {
		$typePrefix = ($this instanceof \Environet\Sys\Admin\Pages\Meteo\MonitoringPointCrud) ? 'meteo' : 'hydro';

		return $plural ? "$typePrefix monitoring points" : "$typePrefix monitoring point";
	}


	/**
	 * @param Select $query
	 *
	 * @return bool|void
	 * @throws QueryException
	 * @throws \Exception
	 */
	protected function modifyListQuery(Select $query) {
		if (!$this->readOwnPermissionName) {
			throw new \Exception('Read own permission not set');
		}

		if (in_array($this->readOwnPermissionName, $this->request->getIdentity()->getAuthorizedPermissions())) {
			// Get the ids of operators the user is part of, and filter the query accordingly
			$operators = UserQueries::getOperatorsOfUser($this->request->getIdentity()->getId());
			$query->whereIn('operatorid', array_column($operators, 'id'), 'operatorId');
		}

		if ($this->request->getQueryParam('country')) {
			$query->where('country = :country')->addParameter('country', $this->request->getQueryParam('country'));
		}

		$query->join('operator', "operator.id = {$this->queriesClass::$tableName}.operatorid");
		$query->select('operator.name as operator');
	}


	/**
	 * @inheritDoc
	 * @throws QueryException
	 */
	protected function userCanView($id) {
		if (in_array($this->readOwnPermissionName, $this->request->getIdentity()->getAuthorizedPermissions())) {
			return $this->userIsOperatorOfMonitoringPoint($id);
		}

		return true;
	}


	/**
	 * @param int $id Monitoring point id
	 *
	 * @return bool
	 * @throws QueryException
	 */
	private function userIsOperatorOfMonitoringPoint(int $id): bool {
		$operatorIds = array_column(UserQueries::getOperatorsOfUser($this->request->getIdentity()->getId()), 'id');
		$query = (new Select())
			->select($this->queriesClass::$tableName . '.id')
			->from($this->queriesClass::$tableName)
			->whereIn('operatorid', $operatorIds, 'operatorId');
		$ids = array_column($query->run(), 'id');

		return in_array($id, $ids);
	}


	/**
	 * @inheritDoc
	 * @throws QueryException
	 */
	protected function userCanEdit($id) {
		if (in_array($this->updateOwnPermissionName, $this->request->getIdentity()->getAuthorizedPermissions())) {
			return $this->userIsOperatorOfMonitoringPoint($id);
		}

		return true;
	}


	/**
	 * @return array
	 * @throws QueryException
	 */
	protected function getOperatorList(): array {
		if (in_array($this->updateOwnPermissionName, $this->request->getIdentity()->getAuthorizedPermissions()) ||
			in_array($this->createOwnPermissionName, $this->request->getIdentity()->getAuthorizedPermissions())) {
			return UserQueries::getOperatorListOfUser($this->request->getIdentity()->getId());
		}

		return OperatorQueries::getOptionList();
	}


	/**
	 * Get the observed property ids from the input string.
	 *
	 * @param string $value
	 *
	 * @return array
	 * @uses \Environet\Sys\Admin\Pages\MonitoringPoint\MonitoringPointCSVMapInterface::getObservedPropertyQueriesClass()
	 */
	protected function parseObservedPropertyIdsFromString(string $value): array {
		$symbols = array_filter(explode(' ', $value));

		return array_filter(array_map(function ($symbol) {
			$obs = $this->getObservedPropertyQueriesClass()::getByColumn('symbol', $symbol);
			if (!$obs) {
				$this->addMessage("Observed property '$symbol' skipped. Could not find in database.", self::MESSAGE_WARNING);

				return null;
			}

			return $obs['id'];
		}, $symbols));
	}


	/**
	 * Parse the input string into an associative array.
	 *
	 * If the line has information about the observed property ids, parse those as well via {@see MonitoringPointCrud::parseObservedPropertyIdsFromString()}.
	 *
	 * @param array $line
	 *
	 * @return array
	 * @uses \Environet\Sys\Admin\Pages\MonitoringPoint\MonitoringPointCSVMapInterface::getCsvColumnMappings()
	 * @uses \Environet\Sys\Admin\Pages\MonitoringPoint\MonitoringPointCSVMapInterface::getObservedPropertiesCsvColumn()
	 * @uses \Environet\Sys\Admin\Pages\MonitoringPoint\MonitoringPointCrud::parseObservedPropertyIdsFromString()
	 */
	protected function dataFromCsvLine(array $line): array {
		$data = [];

		foreach ($this->headingLine as $colNumber => $name) {
			$data[$name] = $line[$colNumber];
		}

		$observedPropertiesColNum = array_search($this->getObservedPropertiesCsvColumn(), $this->headingLine);
		if ($observedPropertiesColNum !== false && isset($line[$observedPropertiesColNum])) {
			$observedPropertyIds = $this->parseObservedPropertyIdsFromString($line[$observedPropertiesColNum]);
			$data['observedProperties'] = $observedPropertyIds;
		} else {
			$data['observedProperties'] = [];
		}

		unset($data[$this->getObservedPropertiesCsvColumn()]);

		return $data;
	}


	/**
	 * Parse and save data from an input CSV file.
	 *
	 * @return string
	 * @uses \Environet\Sys\Admin\Pages\MonitoringPoint\MonitoringPointCrud::dataFromCsvLine()
	 * @uses \Environet\Sys\Admin\Pages\MonitoringPoint\MonitoringPointCSVMapInterface::getGlobalIdName()
	 * @uses \Environet\Sys\Admin\Pages\CrudPage::addMessage()
	 * @uses \Environet\Sys\Admin\Pages\BasePage::redirect()
	 */
	public function csvUpload(): string {
		if ($this->request->isPost()) {
			$csvLines = array_map('str_getcsv', file($_FILES["csv"]['tmp_name']));

			$this->headingLine = array_shift($csvLines);

			foreach ($csvLines as $lineNumber => $line) {
				$csvId = $line[array_search($this->getGlobalIdName(), $this->headingLine)];
				$record = $this->queriesClass::getByColumn($this->getGlobalIdName(), $csvId);
				$recordId = $record ? $record['id'] : null;

				$data = $this->dataFromCsvLine($line);

				try {
					$this->queriesClass::save($data, $recordId);
					$this->addMessage(($recordId ? "Updated" : "Saved") . " " . $line[0], self::MESSAGE_SUCCESS);
				} catch (\Exception $e) {
					$this->addMessage("Could not save $line[0]:" . $e->getMessage(), self::MESSAGE_ERROR);
				}
			}
		}

		return $this->redirect($this->listPagePath);
	}


	/**
	 * @return array[]|null
	 * @throws QueryException
	 */
	protected function getListFilters(): ?array {
		$countries = array_filter((new Select())
			->select('DISTINCT(country)')
			->from($this->queriesClass::$tableName)
			->orderBy('country', 'ASC')
			->run(Query::FETCH_COLUMN));

		return [
			'country' => [
				'label'    => 'Country',
				'options'  => array_combine($countries, $countries),
				'selected' => $this->request->getQueryParam('country') ?? null
			]
		];
	}


}
