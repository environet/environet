<?php


namespace Environet\Sys\Download;

use DateInterval;
use DateTime;
use Environet\Sys\Download\Exceptions\DownloadException;
use Environet\Sys\General\Db\DownloadLogQueries;
use Environet\Sys\General\Db\MonitoringPointQueries;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\Selectors\MonitoringPointSelector;
use Environet\Sys\General\Db\Selectors\ObservedPropertySelector;
use Environet\Sys\General\Exceptions\AccessRuleException;
use Environet\Sys\General\Exceptions\ApiException;
use Environet\Sys\General\Exceptions\InvalidConfigurationException;
use Environet\Sys\General\Exceptions\MissingEventTypeException;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\HttpClient\ApiHandler;
use Environet\Sys\General\Identity;
use Environet\Sys\General\Response;
use Environet\Sys\Xml\CreateErrorXml;
use Environet\Sys\Xml\CreateOutputXml;
use Environet\Sys\Xml\Model\ErrorXmlData;
use Exception;
use Throwable;

/**
 * Class DownloadHandler
 *
 * Handles download API requests
 *
 * @package Environet\Sys\Download
 * @author  SRG Group <dev@srg.hu>
 */
class DownloadHandler extends ApiHandler {

	/**
	 * @inheritDoc
	 */
	protected const HANDLER_PERMISSION = 'api.download';

	/**
	 * @var array
	 */
	protected array $downloadLog;


	/**
	 * Get the queryable monitoring point sets based on the request params and the access rules
	 *
	 * @param $rules
	 * @param $params
	 *
	 * @return array
	 *
	 * @throws AccessRuleException
	 */
	protected function getRequestedSubsets($rules, $params): array {
		$subsets = [];
		foreach ($rules as $rule) {
			$subset = [
				'points' => !empty($params['points']) ? array_intersect($params['points'], $rule['points']->getEUCD()) : $rule['points']->getEUCD(),
				'props'  => !empty($params['symbols']) ? array_intersect($params['symbols'], $rule['props']->getSymbols()) : $rule['props']->getSymbols(),
				'end'    => $params['end'] ?: new DateTime()
			];

			// Skip this set if nothing is requested from this rule's affected monitoring points or observed properties
			if (empty($subset['points']) || empty($subset['props'])) {
				continue;
			}

			// Has start time restriction
			if ($rule['interval']) {
				$limit = (new DateTime('now'))->sub($rule['interval']);

				// Has requested start time
				if (isset($params['start'])) {
					// Requested start time is older than limit
					if ($params['start'] < $limit) {
						if (count($rule['points']->getValues()) > 1) {
							throw new AccessRuleException('Monitoring points ' . implode(',', $rule['points']->getEUCD()) . ' are restricted for the requested time period! The earliest time allowed is ' . $limit->format('Y-m-d H:i:s'));
						} else {
							throw new AccessRuleException('Monitoring point ' . $rule['points']->getEUCD()[0] . ' is restricted for the requested time period! The earliest time allowed is ' . $limit->format('Y-m-d H:i:s'));
						}
					} else {
						// Requested time is allowed, create subset
						$subset['start'] = $params['start'];
					}
				} else {
					// No requested start time, get from earliest
					$subset['start'] = $limit;
				}
			} else {
				// No time restriction, create subset with earliest possible
				$subset['start'] = $params['start'] ?? (new DateTime())->setTimestamp(0);
			}

			$subsets[] = $subset;
		}

		return $subsets;
	}


	/**
	 * Process the existing access for the user's group.
	 * Returns the subsets to be queried.
	 *
	 * @param array $params
	 *
	 * @return array
	 * @throws AccessRuleException
	 * @throws DownloadException
	 * @throws QueryException
	 * @throws ApiException
	 */
	protected function processAccessRules(array $params): array {
		$rules = (new Select())
			->select(['mar.id, mar.operator_id, monitoringpoint_selector as points, observed_property_selector as props, gmar.interval'])
			->from('measurement_access_rules as mar')
			->join('group_measurement_access_rules as gmar', 'mar.id = gmar.measurement_access_rule_id')
			->join('groups', 'gmar.group_id = groups.id')
			->join('users_groups', 'groups.id = users_groups.groupsid')
			->where("users_groups.usersid = {$this->getIdentity()->getId()}")
			->run();

		if (empty($rules)) {
			throw new AccessRuleException('There are no access rules configured or group assigned for this user! Please contact an administrator for support.');
		}

		// Initialize rule member values and check for requested points or selectors
		$availablePoints = [];
		$availableProps = [];
		foreach ($rules as &$rule) {
			$rule['points'] = new MonitoringPointSelector($rule['points'], $params['type'], $rule['operator_id'], $params['countries']);
			$rule['props'] = new ObservedPropertySelector($rule['props'], $params['type'], $rule['operator_id']);
			try {
				$rule['interval'] = $rule['interval'] !== null ? new DateInterval($rule['interval']) : null;
			} catch (Exception $e) {
				throw new DownloadException(402, ["Access rule id: {$rule['id']}."]);
			}

			$availablePoints = array_merge($availablePoints, $rule['points']->getValues());
			$availableProps = array_merge($availableProps, $rule['props']->getValues());
		}

		// Check for monitoring point access
		if (!empty($params['points']) || !empty($params['countries'])) {
			$unauthorizedPoints = MonitoringPointSelector::checkAgainstEUCD($params['type'], $params['points'], $availablePoints, $params['countries']);
			if (!empty($unauthorizedPoints)) {
				if (count($unauthorizedPoints) > 1) {
					throw new AccessRuleException('Monitoring points ' . implode(', ', $unauthorizedPoints) . ' are restricted!');
				} else {
					throw new AccessRuleException("Monitoring point $unauthorizedPoints[0] is restricted!");
				}
			}
		}

		// Check for observation property access
		if (!empty($params['symbols'])) {
			$unauthorizedSymbols = ObservedPropertySelector::checkAgainstSymbols($params['type'], $params['symbols'], $availableProps);
			if (!empty($unauthorizedSymbols)) {
				if (count($unauthorizedSymbols) > 1) {
					throw new AccessRuleException('Observed property symbols ' . implode(', ', $unauthorizedSymbols) . ' are restricted!');
				} else {
					throw new AccessRuleException("Observed property symbol $unauthorizedSymbols[0] is restricted!");
				}
			}
		}

		return $this->getRequestedSubsets($rules, $params);
	}


	/**
	 * Handle the incoming download request.
	 *
	 * Does the following steps:
	 * 1. Fetches and validates the required parameter "type" of the monitoring points requested (hydro or meteo).
	 * 2. Parses and applies optional filter parameters start, end, country and symbol.
	 * 3. Queries the results and generates the output XML containing them.
	 *
	 * @return Response|mixed
	 * @uses \Environet\Sys\General\Db\MonitoringPointQueries::getBuilder()
	 * @uses \Environet\Sys\General\Db\MonitoringPointQueries::getResults()
	 * @uses \Environet\Sys\Xml\CreateOutputXml::generateXml()
	 */
	public function handleRequest() {
		try {
			//Set basic data of download log
			$this->downloadLog = [
				'created_at'         => date('Y-m-d H:i:s'),
				'param_type'         => $this->request->getQueryParam('type'),
				'param_start'        => $this->request->getQueryParam('start'),
				'param_end'          => $this->request->getQueryParam('end'),
				'param_country'      => $this->request->getQueryParam('country'),
				'param_symbol'       => $this->request->getQueryParam('symbol'),
				'param_point'        => $this->request->getQueryParam('point'),
				'request_attributes' => ($extraParams = $this->request->getExtraParams()) ? json_encode($extraParams) : null,
				'request_ip'         => $this->request->getClientIp()
			];

			$this->authorizeRequest();

			// Observation point type check
			$type = $this->request->getQueryParam('type', false);
			if (!$type) {
				// Missing type
				throw new DownloadException(302);
			}
			$type = filter_var($type, FILTER_SANITIZE_STRING);
			if (!in_array($type, [MonitoringPointQueries::TYPE_HYDRO, MonitoringPointQueries::TYPE_METEO])) {
				// Invalid type
				throw new DownloadException(303);
			}

			// Every check passed, start preparing the response and processing access restrictions
			$queryBuilder = MonitoringPointQueries::getBuilder();
			$queryBuilder->setType($type);

			// Prepare params with access restrictions
			$params = [];

			// Optional filters
			$startTime = $this->request->getQueryParam('start', false);
			if ($startTime) {
				try {
					$params['start'] = new DateTime(filter_var($startTime, FILTER_SANITIZE_STRING));
				} catch (Throwable $e) {
					throw new DownloadException(304);
				}
			} else {
				$params['start'] = (new DateTime())->modify('today');
			}

			$endTime = $this->request->getQueryParam('end', false);
			if ($endTime) {
				try {
					$params['end'] = new DateTime(filter_var($endTime, FILTER_SANITIZE_STRING));
				} catch (Throwable $e) {
					throw new DownloadException(305);
				}
			} else {
				$params['end'] = (new DateTime())->modify('+1 day')->modify('today');
			}

			$params['points'] = $this->parseArrayParam('point');
			$params['symbols'] = $this->parseArrayParam('symbol');
			$params['countries'] = $this->parseArrayParam('country');

			// Permission check
			if (!$this->getIdentity()->hasPermissions([Identity::ADMIN_PERMISSION])) {
				$params['type'] = $type === 'hydro' ? MPOINT_TYPE_HYDRO : MPOINT_TYPE_METEO;
				// Overwrites params with the allowed data for this user
				try {
					$queryBuilder->createSubsets($this->processAccessRules($params));
				} catch (AccessRuleException $exception) {
					throw new DownloadException(401, [$exception->getMessage()]);
				}
			} else {
				$queryBuilder->setMonitoringPointsByEUCD($params['points']);
				$queryBuilder->setObservedPropertiesBySymbol($params['symbols']);
				$queryBuilder->setStartTime($params['start']);
				$queryBuilder->setEndTime($params['end']);
			}

			$queryBuilder->setCountries($params['countries']);

			$queryMeta = [
				'startTime' => $startTime,
				'endTime' => $endTime,
			];
			$response = (new Response((new CreateOutputXml())->generateXml($queryBuilder->getResults(), $queryMeta)))->setHeaders(['Content-type: application/xml']);

			$this->saveDownloadLog($response);

			return $response;
		} catch (DownloadException $e) {
			// Create ErrorResponse xml
			http_response_code(400);

			exception_logger($e);

			$response = (new Response((new CreateErrorXml())->generateXml($e->getErrorXmlData())->asXML()))
				->setHeaders(['Content-type: application/xml'])
				->setStatusCode(400);

			$this->saveDownloadLog($response, $e->getCode());

			return $response;
		} catch (Throwable $e) {
			// Create ErrorResponse xml
			http_response_code(500);

			exception_logger($e);

			$response = (new Response((new CreateErrorXml())->generateXml([new ErrorXmlData(500, $e->getMessage())])->asXML()))
				->setHeaders(['Content-type: application/xml'])
				->setStatusCode(500);

			$this->saveDownloadLog($response, $e->getCode());

			return $response;
		}
	}


	/**
	 * Add some extra parameters to download log, and save it
	 *
	 * @param Response $response
	 * @param int|null $errorCode
	 *
	 * @return void
	 */
	protected function saveDownloadLog(Response $response, ?int $errorCode = null) {
		if ($this->downloadLog) {
			$this->downloadLog['user_id'] = $this->identity ? $this->identity->getId() : null;
			$this->downloadLog['response_status'] = $response->getStatusCode();
			$this->downloadLog['error_code'] = $errorCode;
			$this->downloadLog['response_size'] = $response->getSize();
			$this->downloadLog['execution_time'] = round((microtime(true) - REQUEST_START_TIME) * 1000);

			try {
				DownloadLogQueries::save($this->downloadLog);
			} catch (MissingEventTypeException|InvalidConfigurationException|QueryException $e) {
				//Muted exception of saving download query
			}
		}
	}


}
