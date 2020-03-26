<?php

namespace Environet\Sys\Admin\Pages\Meteo;

use Environet\Sys\Admin\Pages\CrudPage;
use Environet\Sys\General\Db\MeteoMonitoringPointQueries;
use Environet\Sys\General\Db\MeteoObservedPropertyQueries;
use Environet\Sys\General\Db\MeteoStationClassificationQueries;
use Environet\Sys\General\Db\OperatorQueries;
use Environet\Sys\General\Exceptions\HttpNotFoundException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\Response;

/**
 * Class MonitoringPointCrud
 * @package Environet\Sys\Admin\Pages\Hydro
 */
class MonitoringPointCrud extends CrudPage {

	/**
	 * @inheritdoc
	 */
	protected $queriesClass = MeteoMonitoringPointQueries::class;

	/**
	 * @inheritdoc
	 */
	protected $indexTemplate = '/meteo/monitoringpoint/index.phtml';

	/**
	 * @inheritdoc
	 */
	protected $formTemplate = '/meteo/monitoringpoint/form.phtml';

	/**
	 * @inheritdoc
	 */
	protected $showTemplate = '/meteo/monitoringpoint/show.phtml';

	/**
	 * @inheritdoc
	 */
	protected $listPagePath = '/admin/meteo/monitoring-points';

	/**
	 * @inheritdoc
	 */
	protected $successAddMessage = 'Monitoring point successfully saved';


	/**
	 * List page action.
	 *
	 * @return Response
	 * @throws RenderException
	 */
	public function list(): Response {
		return $this->renderListPage();
	}


	/**
	 * Show page action.
	 *
	 * @return Response
	 * @throws RenderException
	 * @throws HttpNotFoundException
	 */
	public function show(): Response {
		return $this->renderShowPage();
	}


	/**
	 * @inheritDoc
	 *
	 * @return array
	 */
	protected function formContext(): array {
		return [
			'classifications' => MeteoStationClassificationQueries::getOptionList('value'),
			'operators' => OperatorQueries::getOptionList('name'),
			'observedProperties' => MeteoObservedPropertyQueries::getOptionList('symbol')
		];
	}


	/**
	 * @inheritDoc
	 */
	protected function validateData(array $data): bool {
		$valid = true;

		if (!validate($data, 'name', REGEX_NAME, true)) {
			$this->addMessage('Monitoring point name is empty, or format is invalid', self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!$data['classification']) {
			$this->addMessage(__('Classification is required'), self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!$data['operator']) {
			$this->addMessage(__('Operator is required'), self::MESSAGE_ERROR);
			$valid = false;
		}

		if (!$data['observedProperties']) {
			$this->addMessage(__('Observed property is required'), self::MESSAGE_ERROR);
			$valid = false;
		}

		return $valid;
	}


}
