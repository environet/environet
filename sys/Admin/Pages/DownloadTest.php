<?php

namespace Environet\Sys\Admin\Pages;

use DateTime;
use Environet\Sys\Config;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\HttpBadRequestException;
use Environet\Sys\General\Exceptions\PKIException;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\Exceptions\RenderException;
use Environet\Sys\General\HttpClient\Exceptions\HttpClientException;
use Environet\Sys\General\HttpClient\HttpClient;
use Environet\Sys\General\HttpClient\Request;
use Environet\Sys\General\PKI;
use Environet\Sys\General\Response;
use Exception;
use InvalidArgumentException;

/**
 * Class DownloadTest
 *
 * Displays a not listed page for testing download requests
 *
 * @package Environet\Sys\Admin\Pages
 * @author  SRG Group <dev@srg.hu>
 */
class DownloadTest extends BasePage {

	/**
	 * @var array Array of countries
	 */
	protected $countries;

	/**
	 * @var array Array of monitoring points
	 */
	protected $points;

	/**
	 * @var array Observation property symbols
	 */
	protected $symbols;

	/**
	 * @var array Available users
	 */
	protected $users;

	/**
	 * @var string Token for testing purposes
	 */
	protected $token = 'test_request';


	/**
	 * Get the available countries and EUCD identifiers per monitoring point types.
	 *
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Select
	 */
	protected function fetchCountriesAndEUCD() {
		$this->countries = ['hydro' => [], 'meteo' => []];
		$this->points = ['hydro' => [], 'meteo' => []];
		$countries = (new Select())
			->from('hydropoint')
			->select('country, eucd_wgst')
			->run();

		foreach ($countries as $country) {
			$this->countries['hydro'][$country['country']] = $country['country'];
			$this->points['hydro'][$country['eucd_wgst']] = $country['eucd_wgst'];
		}
		$this->countries['hydro'] = array_unique($this->countries['hydro']);

		$countries = (new Select())
			->from('meteopoint')
			->select('country, eucd_pst')
			->run();

		foreach ($countries as $country) {
			$this->countries['meteo'][$country['country']] = $country['country'];
			$this->points['meteo'][$country['eucd_pst']] = $country['eucd_pst'];
		}
		$this->countries['meteo'] = array_unique($this->countries['meteo']);
	}


	/**
	 * Get the available observed property symbols per monitoring point types.
	 *
	 * @throws QueryException
	 * @uses \Environet\Sys\General\Db\Query\Select
	 */
	protected function fetchSymbols() {
		$this->symbols = ['hydro' => [], 'meteo' => []];
		$symbols = (new Select())
			->from('hydro_observed_property')
			->select('symbol')
			->groupBy('symbol')
			->run();

		foreach ($symbols as $symbol) {
			$this->symbols['hydro'][$symbol['symbol']] = $symbol['symbol'];
		}

		$symbols = (new Select())
			->from('meteo_observed_property')
			->select('symbol')
			->groupBy('symbol')
			->run();

		foreach ($symbols as $symbol) {
			$this->symbols['meteo'][$symbol['symbol']] = $symbol['symbol'];
		}
	}


	/**
	 * Handle the download test request.
	 *
	 * If it's a GET request, renders the download test page
	 * If it's a POST request, it forwards the request parameters to the download API via {@see DownloadTest::sendData()}.
	 *
	 * @return Response|null
	 * @throws HttpBadRequestException
	 * @throws QueryException
	 * @throws RenderException
	 * @uses \Environet\Sys\Admin\Pages\DownloadTest::sendData()
	 */
	public function handle(): ?Response {
		// Get available countries and monitoring point identifiers
		$this->fetchCountriesAndEUCD();

		// Get observation property symbols (names)
		$this->fetchSymbols();

		// Create observed property options
		$this->users = (new Select())->from('users')->run();
		$this->users = array_combine(array_column($this->users, 'username'), array_column($this->users, 'username'));

		$response = $error = null;
		if ($this->request->isPost()) {
			// Posted form, check data, and send it to the API
			if (!$this->checkCsrf()) {
				// CSRF error
				throw new HttpBadRequestException();
			}

			if (!$_POST['type']) {
				throw new HttpBadRequestException('Invalid type');
			}

			try {
				// Send the data with a http client, and store the response body in a variable
				$response = $this->sendData();
			} catch (HttpClientException $e) {
				// Store error response of the request in $error var
				$error = $e->getMessage();
			}
		}

		// Render the form
		return $this->render('/download_test.phtml', [
			'hydro'    => [
				'countries' => $this->countries['hydro'],
				'points'    => $this->points['hydro'],
				'symbols'   => $this->symbols['hydro'],
			],
			'meteo'    => [
				'countries' => $this->countries['meteo'],
				'points'    => $this->points['meteo'],
				'symbols'   => $this->symbols['meteo'],
			],
			'users'    => $this->users,
			'response' => $response,
			'error'    => $error
		]);
	}


	/**
	 * Send the configured download request.
	 *
	 * Generates a signature header based on the current user's private key and the test token ({@see DownloadTest::$token}).
	 *
	 * @throws Exception
	 * @uses \Environet\Sys\Admin\Pages\DownloadTest::generateSignatureHeader()
	 * @uses \Environet\Sys\General\HttpClient\HttpClient::sendRequest()
	 */
	protected function sendData() {
		$type = $_POST['type'];
		$countries = $_POST["$type-country"] ?? false;
		$points = $_POST["$type-point"] ?? false;
		$symbols = $_POST["$type-symbol"] ?? false;
		$username = $_POST['username'] ?? null;
		$start = $_POST['start'] ?? false;
		$end = $_POST['end'] ?? false;
		$params = "&type=$type";

		if ($countries) {
			if (is_array($countries)) {
				$params .= '&country[]=' . implode('&country[]=', $countries);
			} else {
				$params .= "&country[]={$countries}";
			}
		}

		if ($points) {
			if (is_array($points)) {
				$params .= '&point[]=' . implode('&point[]=', $points);
			} else {
				$params .= "&point[]={$points}";
			}
		}

		if ($symbols) {
			if (is_array($symbols)) {
				$params .= '&symbol[]=' . implode('&symbol[]=', $symbols);
			} else {
				$params .= "&symbol[]={$symbols}";
			}
		}

		if ($start) {
			$params .= '&start=' . urlencode((new DateTime($start))->format('c'));
		}

		if ($end) {
			$params .= '&end=' . urlencode((new DateTime($end))->format('c'));
		}

		$apiHost = Config::getInstance()->getDatanodeDistHost();

		// Create a request
		$request = new Request(
			sprintf(
				"%s/download?token=%s%s",
				rtrim($apiHost, '/'),
				md5($this->token),
				$params
			)
		);
		$request->setMethod('GET');

		// Add generated auth header with signature
		$request->addHeader('Authorization', $this->generateSignatureHeader($username));
		// Send request
		$client = new HttpClient();

		return $client->sendRequest($request);
	}


	/**
	 * Create the download API request's auth header
	 *
	 * Uses the username and the signature generated by {@see PKI}
	 *
	 * @param string $username
	 *
	 * @return string
	 * @throws InvalidArgumentException
	 * @throws PKIException
	 * @throws Exception
	 * @uses \Environet\Sys\General\PKI::authHeaderWithSignature()
	 * @uses \Environet\Sys\General\PKI::generateSignature()
	 */
	protected function generateSignatureHeader($username): string {
		$privateKeyFile = SRC_PATH . '/data/test_private_keys/' . $username . '.key';
		if (!file_exists($privateKeyFile)) {
			throw new Exception('Test private key under "data/test_private_keys" folder doesn\'t exist for this user');
		}

		$pkiLib = new PKI();
		$signature = $pkiLib->generateSignature(md5($this->token), file_get_contents($privateKeyFile));

		return $pkiLib->authHeaderWithSignature($signature, $username);
	}
}