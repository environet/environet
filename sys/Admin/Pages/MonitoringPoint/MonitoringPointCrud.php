<?php

namespace Environet\Sys\Admin\Pages\MonitoringPoint;

use Environet\Sys\Admin\Pages\CrudPage;

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

		foreach ($this->getCsvColumnMappings() as $name => $colNumber) {
			$data[$name] = $line[$colNumber];
		}

		if (isset($line[$this->getObservedPropertiesCsvColumn()])) {
			$observedPropertyIds = $this->parseObservedPropertyIdsFromString($line[$this->getObservedPropertiesCsvColumn()]);
			$data['observedProperties'] = $observedPropertyIds;
		} else {
			$data['observedProperties'] = [];
		}

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

			foreach ($csvLines as $lineNumber => $line) {
				$data = $this->dataFromCsvLine($line);

				$record = $this->queriesClass::getByColumn($this->getGlobalIdName(), $line[2]);
				$recordId = $record ? $record['id'] : null;

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
}