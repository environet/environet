<?php


namespace Environet\Sys\Api;

use Environet\Sys\General\Db\HydroMonitoringPointQueries;
use Environet\Sys\General\Db\MeteoMonitoringPointQueries;
use Environet\Sys\General\Db\UserQueries;
use Environet\Sys\General\Exceptions\ApiException;
use Environet\Sys\General\Exceptions\HttpNotFoundException;
use Environet\Sys\General\HttpClient\ApiHandler;
use Environet\Sys\General\Response;
use Exception;

/**
 * Class JsonApiHandler
 *
 * Handles API requests
 *
 * @package Environet\Sys\Download
 * @author  SRG Group <dev@srg.hu>
 */
class JsonApiHandler extends ApiHandler {

	/**
	 * @return string
	 */
	private function requestPath(): string {
		$parts = $this->request->getPathParts();
		array_shift($parts);
		return implode('/', $parts);
	}


	/**
	 * Handle the incoming json api request.
	 *
	 * @return Response
	 */
	public function handleRequest(): Response {
		try {
			$this->authorizeRequest();

			switch ($this->requestPath()) {
				case 'monitoring-points':
					$operatorIds = null;
					if (!$this->getIdentity()->hasPermissions(['admin.hydro.monitoringpoints.read'])
						&& $this->getIdentity()->hasPermissions(['admin.hydro.monitoringpoints.readown'])) {
						$operatorIds = array_column(UserQueries::getOperatorsOfUser($this->request->getIdentity()->getId()), 'id');
					}

					$results = [];

					//Find active points only
					$results['hydro'] = HydroMonitoringPointQueries::all($operatorIds, true);
					$results['meteo'] = MeteoMonitoringPointQueries::all($operatorIds, true);
					return $this->jsonResponse(json_encode($results), 200);
				default:
					throw new HttpNotFoundException('API route not found');
			}
		} catch (HttpNotFoundException $e) {
			return $this->jsonResponse(json_encode(['error' => $e->getMessage()]), 404);
		} catch (ApiException $e) {
			return $this->jsonResponse(json_encode(['error' => $e->getMessage()]), 400);
		} catch (Exception $e) {
			return $this->jsonResponse(json_encode(['error' => $e->getMessage()]), 500);
		}
	}


	/**
	 * @param $contents
	 * @param $statusCode
	 *
	 * @return Response
	 */
	private function jsonResponse($contents, $statusCode): Response {
		return (new Response($contents))
			->setStatusCode($statusCode)
			->setHeaders(['Content-type: application/json']);
	}


}
