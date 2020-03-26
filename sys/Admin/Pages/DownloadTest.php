<?php
/**
 * Interface DownloadTest
 *
 * @package   Environet\Sys\Admin\Pages
 * @author    SRG Group <dev@srg.hu>
 * @copyright 2020 SRG Group Kft.
 */

namespace Environet\Sys\Admin\Pages;

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
 * @package   Environet\Sys\Admin\Pages
 * @author    SRG Group <dev@srg.hu>
 * @copyright 2020 SRG Group Kft.
 */
class DownloadTest extends BasePage {

	/**
	 * @var array Array of countries
	 */
	private $countries;

	/**
	 * @var array Observation property symbols
	 */
	private $symbols;

	/**
	 * @var array Available users
	 */
	private $users;

	/**
	 * @var string Token for testing purposes
	 */
	private $token = 'test_request';


	/**
	 * @return Response|null
	 * @throws HttpBadRequestException
	 * @throws QueryException
	 * @throws RenderException
	 */
	public function handle(): ?Response {
		// Get available countries
		$this->countries = [];
		$countries = (new Select())
			->from('hydropoint')
			->select('country')
			->groupBy('country')
			->union(
				(new Select())
					->from('meteopoint')
					->select('country')
					->groupBy('country')
			)
			->run();

		foreach ($countries as $country) {
			$this->countries[$country['country']] = $country['country'];
		}

		// Get observation property symbols (names)
		$this->symbols = [];
		$symbols = (new Select())
			->from('hydro_observed_property')
			->select('symbol')
			->groupBy('symbol')
			->union(
				(new Select())
					->from('meteo_observed_property')
					->select('symbol')
					->groupBy('symbol')
			)
			->run();

		foreach ($symbols as $symbol) {
			$this->symbols[$symbol['symbol']] = $symbol['symbol'];
		}

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
			'countries' => $this->countries,
			'symbols'   => $this->symbols,
			'users'     => $this->users,
			'response'  => $response,
			'error'     => $error
		]);
	}


	/**
	 * @throws Exception
	 */
	protected function sendData() {
		$countries = $_POST['country'] ?? false;
		$symbols = $_POST['symbol'] ?? false;
		$username = $_POST['username'] ?? null;
		$start = $_POST['start'] ?? false;
		$end = $_POST['end'] ?? false;
		$params = '';

		if ($countries) {
			if (is_array($countries)) {
				$params .= '&country[]=' . implode('&country[]=', $countries);
			} else {
				$params .= "&country[]={$countries}";
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
			$params .= '&start=' . urlencode((new \DateTime($start))->format('c'));
		}

		if ($end) {
			$params .= '&start=' . urlencode((new \DateTime($end))->format('c'));
		}

		$apiHost = Config::getInstance()->getDatanodeApiHost();

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
	 * Create the auth header with the username and the signature
	 *
	 * @param string $username
	 *
	 * @return string
	 * @throws InvalidArgumentException
	 * @throws PKIException
	 * @throws Exception
	 */
	protected function generateSignatureHeader($username): string {
		$privateKeyFile = SRC_PATH . '/data/test_private_keys/' . $username . '.key';
		if (!file_exists($privateKeyFile)) {
			throw new Exception('Test private key under "data/test_private_keys" folder doesn\'t exist for this user');
		}

		$pkiLib = new PKI();
		$signature = $pkiLib->generateSignature($this->token, file_get_contents($privateKeyFile));

		return $pkiLib->authHeaderWithSignature($signature, $username);
	}
}