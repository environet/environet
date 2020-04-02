<?php

namespace Environet\Sys\Admin\Pages\MonitoringPoint;

use Environet\Sys\Admin\Pages\CrudPage;

class MonitoringPointCrud extends CrudPage
{
	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'Monitoring point successfully saved';

	private function parseObservedPropertyIdsFromString(string $value): array {
		$symbols = array_filter(explode(' ', $value));

		return array_filter(array_map(function($symbol) {
			$obs = $this->observedPropertyQueriesClass::getByColumn('symbol', $symbol);
			if(!$obs){
				$this->addMessage("Observed property '$symbol' skipped. Could not find in database.", self::MESSAGE_WARNING);
				return null;
			}
			return $obs['id'];
		}, $symbols));
	}

	private function dataFromCsvLine(array $line): array {
		$data = [];

		foreach ($this->csvColumnMappings as $name => $colNumber) {
			$data[$name] = $line[$colNumber];
		}

		if(isset($line[$this->observedPropertiesCsvColumn])) {
			$observedPropertyIds = $this->parseObservedPropertyIdsFromString($line[4]);
			$data['observedProperties'] = $observedPropertyIds;
		} else {
			$data['observedProperties'] = [];
		}

		return $data;
	}

	public function csvUpload(): string
	{
		if ($this->request->isPost()) {

			$csvLines = array_map('str_getcsv', file($_FILES["csv"]['tmp_name']));

			foreach ($csvLines as $lineNumber => $line) {
				$data = $this->dataFromCsvLine($line);

				$record = $this->queriesClass::getByColumn($this->globalIdName, $line[2]);
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