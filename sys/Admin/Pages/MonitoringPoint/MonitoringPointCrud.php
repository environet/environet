<?php

namespace Environet\Sys\Admin\Pages\MonitoringPoint;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\OperatorQueries;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\UserQueries;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;
use Exception;

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
	protected $successAddMessage = 'Monitoring point successfully added';

	/**
	 * @inheritdoc
	 */
	protected $successEditMessage = 'Monitoring point successfully saved';

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
	 * @var string
	 */
	protected $observedPropertiesCsvColumn = 'observed_properties';

	/**
	 * @inheritdoc
	 */
	protected $csvUploadTemplate = '/parts/csvupload.phtml';


	/**
	 * Get enums for CSV upload page
	 * @return array
	 */
	abstract protected function getCsvEnums(): array;


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
	 * Get allowe operator ids of the user
	 *
	 * @return array|null
	 * @throws QueryException
	 */
	protected function getAllowedOperatorIds(): ?array {
		if (in_array($this->readOwnPermissionName, $this->request->getIdentity()->getAuthorizedPermissions()) ||
			in_array($this->createOwnPermissionName, $this->request->getIdentity()->getAuthorizedPermissions()) ||
			in_array($this->updateOwnPermissionName, $this->request->getIdentity()->getAuthorizedPermissions())
		) {
			// Get the ids of operators the user is part of, and filter the query accordingly
			$operators = UserQueries::getOperatorsOfUser($this->request->getIdentity()->getId());
			return $operators ? array_column($operators, 'id') : [];
		}
		//All
		return null;
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

		$allowedOperatorIds = $this->getAllowedOperatorIds();
		if (!is_null($allowedOperatorIds)) {
			// Get the ids of operators the user is part of, and filter the query accordingly
			$query->whereIn('operatorid', $allowedOperatorIds, 'operatorId');
		}

		if ($this->request->getQueryParam('country')) {
			$query->where('country = :country')->addParameter('country', $this->request->getQueryParam('country'));
		}

		if ($this->request->getQueryParam('is_active') !== null) {
			$value = $this->request->getQueryParam('is_active') === '_0' ? false : true;
			$query->where('is_active = :isActive')->addParameter('isActive', $value);
		}

        if ($this->request->getQueryParam('is_out_of_order') !== null) {
            $value = $this->request->getQueryParam('is_out_of_order') === '_0' ? false : true;
            $query->where('is_out_of_order = :isOutOfOrder')->addParameter('isOutOfOrder', $value);
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
		$operatorIds = $this->getAllowedOperatorIds() ?: [];
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
	 * @throws Exception
	 * @uses \Environet\Sys\Admin\Pages\MonitoringPoint\MonitoringPointCSVMapInterface::getObservedPropertiesCsvColumn()
	 * @uses \Environet\Sys\Admin\Pages\MonitoringPoint\MonitoringPointCrud::parseObservedPropertyIdsFromString()
	 * @uses \Environet\Sys\Admin\Pages\MonitoringPoint\MonitoringPointCSVMapInterface::getCsvColumnMappings()
	 */
	protected function dataFromCsvLine(array $line): array {
		if (count($line) !== count($this->headingLine)) {
			throw new Exception('CSV column number not correct');
		}
		$data = array_combine($this->headingLine, $line);

		if (isset($data[$this->observedPropertiesCsvColumn])) {
			$observedPropertyIds = $this->parseObservedPropertyIdsFromString($data[$this->observedPropertiesCsvColumn]);
			$data['observedProperties'] = $observedPropertyIds;
		} else {
			$data['observedProperties'] = [];
		}

		$data['river_basin_id'] = $data['river_basin'];

		unset($data[$this->observedPropertiesCsvColumn]);
		return $data;
	}


	/**
	 * @return string[]
	 */
	public function getCsvColumns(): array {
		return [
			'name' => 'Station name [text]',
			'location' => 'Location [text]',
			'country' => '2-char country code [text]',
			'operator' => ['title' => 'Operator ID [ID]', 'outField' => 'operatorid'],
			'riverbank' => ['title' => 'Riverbank ID [ID]', 'outField' => 'bankid'],
			'river' => ['title' => 'River ID [ID]', 'outField' => 'eucd_riv'],
			'vertical_reference' => 'Vertical reference [text]',
			'long' => 'Longitude coordinate [number]',
			'lat' => 'Latitude coordinate [number]',
			'z' => 'Z oordinate [number]',
			'maplong' => 'Map longitude coordinate [number]',
			'maplat' => 'Map longitude coordinate [number]',
			'start_time' => 'Start time [yyyy-mm-dd]',
			'end_time' => 'End time [yyyy-mm-dd]',
			'utc_offset' => 'UTC offset [number]',
			'river_basin' => ['title' => 'River basin id/code [number]', 'outField' => 'river_basin_id'],
			$this->observedPropertiesCsvColumn => 'Observered properties [text]'
		];
	}


	/**
	 * Parse and save data from an input CSV file.
	 *
	 * @return string
	 * @throws QueryException
	 * @throws RenderException
	 * @uses \Environet\Sys\Admin\Pages\MonitoringPoint\MonitoringPointCrud::dataFromCsvLine()
	 * @uses \Environet\Sys\Admin\Pages\MonitoringPoint\MonitoringPointCSVMapInterface::getGlobalIdName()
	 * @uses \Environet\Sys\Admin\Pages\CrudPage::addMessage()
	 * @uses \Environet\Sys\Admin\Pages\BasePage::redirect()
	 */
	public function csvUpload(): string {
		if ($this->request->isPost()) {
			$csvLines = array_map('str_getcsv', file($_FILES["csv"]['tmp_name']));

			$this->headingLine = array_shift($csvLines);

			$updated = [];
			$added = [];
			$errorLines = [];
			$allowedOperatorIds = $this->getAllowedOperatorIds();
			foreach ($csvLines as $lineNumber => $line) {
				$csvId = $line[array_search($this->getGlobalIdName(), $this->headingLine)];
				$operatorId = $line[array_search('operator', $this->headingLine)];
				$record = $this->queriesClass::getByNcdAndOperator($this->getGlobalIdName(), $csvId, $operatorId);
				$recordId = $record ? $record['id'] : null;

				//Allow operator only if user is allowed to edit the point
				if ($recordId && !is_null($allowedOperatorIds) && !in_array($record['operatorid'], $allowedOperatorIds)) {
					$errorLines[] = $csvId;
					continue;
				}

				$data = $this->dataFromCsvLine($line);

				try {
					$this->queriesClass::save($data, $recordId);
					$this->csvUploadAfterSave($data, $recordId);
					if ($recordId) {
						$updated[] = $csvId;
					} else {
						$added[] = $csvId;
					}
				} catch (\Exception $e) {
					$errorLines[] = $csvId;
				}
			}

			if (!empty($added)) {
				$this->addMessage('Added points: ' . count($added), self::MESSAGE_SUCCESS);
			}
			if (!empty($updated)) {
				$this->addMessage('Updated points: ' . count($updated), self::MESSAGE_SUCCESS);
			}
			if (!empty($errorLines)) {
				$this->addMessage('Error with points: ' . implode(',', $errorLines), self::MESSAGE_ERROR);
			}

			return $this->redirect($this->getListPageLinkWithState());
		}

		$pageTitle = 'CSV upload '.$this->getEntityName(true);

		//Find some enums for upload
		$query = (new Select())
			->select($this->queriesClass::$tableName . '.operatorid')
			->from($this->queriesClass::$tableName);
		$this->modifyListQuery($query);
		$operators = $query->run();
		$operators = array_combine(array_column($operators, 'operatorid'), array_column($operators, 'operator'));

		$enums = array_merge(
			[
				['title' => 'Operators', 'options' => $operators],
				['title' => 'Observed properties', 'options' => $this->getObservedPropertyQueriesClass()::getOptionList('description', 'symbol')]
			],
			$this->getCsvEnums()
		);

		$csvColumns = array_filter(array_map(function ($config) {
			return is_string($config) ? $config : $config['title'] ?? null;
		}, $this->getCsvColumns()));

		return $this->render($this->csvUploadTemplate, compact('pageTitle', 'enums', 'csvColumns'));
	}


	/**
	 * Possibility to save some other data after records is saved after upload
	 *
	 * @param $data
	 * @param $mpointId
	 */
	protected function csvUploadAfterSave($data, $mpointId) {
	}


	/**
	 * Download CSV of current monitoring points
	 *
	 * @throws QueryException
	 */
	public function csvDownload() {
		//Base query with joins and conditions
		$query = (new Select())
			->select($this->queriesClass::$tableName . '.*')
			->from($this->queriesClass::$tableName);

		//Filter to allowed points
		$this->modifyListQuery($query);
		$query->orderBy($this->getGlobalIdName(), 'ASC');
		$records = $query->run();

		//Generate CSV
		$csv = fopen('php://temp', 'r+');
		$csvColumns = $this->getCsvColumns();
		fputcsv($csv, array_keys($csvColumns));
		foreach ($records as $record) {
			$line = [];
			foreach ($csvColumns as $field => $titleOrConfig) {
				if (method_exists($this, 'getCsvField') && ($getterValue = $this->getCsvField($record['id'], $field)) !== false) {
					//Check if custom getter returns any valid value. If yes, this will be used
					$line[$field] = $getterValue;
				} elseif ($field === $this->observedPropertiesCsvColumn) {
					//Observed property special column
					$line[$field] = implode(' ', $this->getObservedPropertyQueriesClass()::getSymbolsByPoint($record['id']));
				} else {
					//Add colum with field, or custom field mapping
					$outField = (is_array($titleOrConfig) && isset($titleOrConfig['outField'])) ? $titleOrConfig['outField'] : $field;
					$line[$field] = $record[$outField] ?? null;
				}
			}

			fputcsv($csv, $line);
		}
		rewind($csv);

		$response = new Response(stream_get_contents($csv));
		fclose($csv);

		$response->addHeader('Content-Type: text/csv');
		$fileName = str_replace(' ', '_', $this->getEntityName(true));
		$response->addHeader('Content-Disposition: attachment; filename="'.$fileName.'.csv"');

		return $response;
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
			'is_active' => [
				'label'    => 'Is active',
				'options'  => ['_0' => 'Inactive', '_1' => 'Active'],
				'selected' => $this->request->getQueryParam('is_active') ?? null
			],
            'is_out_of_order' => [
                'label'    => 'Is Ouf of Order',
                'options'  => ['_0' => 'No', '_1' => 'Yes'],
                'selected' => $this->request->getQueryParam('is_out_of_order') ?? null
            ],
			'country' => [
				'label'    => 'Country',
				'options'  => array_combine($countries, $countries),
				'selected' => $this->request->getQueryParam('country') ?? null
			]
		];
	}


}
