<?php


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
use Environet\Sys\General\HttpClient\Response as HttpClientResponse;
use Environet\Sys\General\PKI;
use Environet\Sys\General\Response;
use Environet\Sys\Xml\CreateInputXml;
use Environet\Sys\Xml\Exceptions\CreateInputXmlException;
use Environet\Sys\Xml\Model\InputXmlData;
use Environet\Sys\Xml\Model\InputXmlPropertyData;
use Exception;
use InvalidArgumentException;

/**
 * Class UploadTest
 *
 * Admin page for upload testing
 *
 * @package Environet\Sys\Admin\Pages
 * @author  Ádám Bálint <adam.balint@srg.hu>
 */
class UploadTest extends BasePage {

	/**
	 * @var array Array of monitoring point options
	 */
	private $mpoints;

	/**
	 * @var array Array of observed property options
	 */
	private $properties;

	/**
	 * @var array Array of user options
	 */
	private $users;


	/**
	 * Handle the upload test request.
	 *
	 * If GET, it displays the form with some pre-selected date.
	 * If POST, it creates an XML based on the form's data, and post it to the upload API
	 *
	 * @return mixed|void
	 * @throws HttpBadRequestException
	 * @throws QueryException
	 * @throws RenderException
	 * @throws PKIException
	 */
	public function handle(): ?Response {

		//Create monitoring point options
		$this->mpoints = (new Select())->from('hydropoint')->run();
		$this->mpoints = array_combine(array_column($this->mpoints, 'eucd_wgst'), array_column($this->mpoints, 'eucd_wgst'));

		//Create observed property options
		$this->properties = (new Select())->from('hydro_observed_property')->run();
		$this->properties = array_combine(array_column($this->properties, 'symbol'), array_column($this->properties, 'description'));

		//Create observed property options
		$this->users = (new Select())->from('users')->run();
		$this->users = array_combine(array_column($this->users, 'username'), array_column($this->users, 'username'));

		$response = $error = null;
		if ($this->request->isPost()) {
			//Posted form, check data, and send it to the API
			if (!$this->checkCsrf()) {
				//CSRF error
				throw new HttpBadRequestException();
			}
			try {
				//Send the data with a http client, and store the response body in a varialbe
				$response = $this->sendData();
			} catch (HttpClientException | CreateInputXmlException $e) {
				//Store error response of the request in $error var
				$error = $e->getMessage();
			}
		}

		// Render the form
		return $this->render('/upload_test.phtml', [
			'mpoints' => $this->mpoints,
			'properties' => $this->properties,
			'users' => $this->users,
			'response' => $response,
			'error' => $error
		]);
	}


	/**
	 * Create an input-XML based on the form's data, and send it to the API with the HTTP client
	 *
	 * @return HttpClientResponse
	 * @throws CreateInputXmlException
	 * @throws HttpClientException
	 * @throws PKIException
	 */
	protected function sendData(): HttpClientResponse {
		$mpointId = $_POST['mpoint'] ?? null;
		$propertySymbol = $_POST['property'] ?? null;
		$username = $_POST['username'] ?? null;

		//Process values "text"
		$values = array_values(array_filter(array_map(function ($row) {
			$rowExploded = explode(',', $row);
			if (empty($rowExploded)) {
				//Skip invalid rows
				return null;
			}
			return [
				'time' => trim($rowExploded[0]),
				'value' => (float) trim($rowExploded[1]),
			];
		}, explode("\n", $_POST['values'] ?? null))));

		//Create XML
		$creator = new CreateInputXml();
		$property = new InputXmlPropertyData($propertySymbol, $values);
		$xml = $creator->generateXml(new InputXmlData($mpointId, [$property]))->asXML();

		//Create a request
		$apiHost = Config::getInstance()->getDatanodeApiHost();
		$request = new Request(rtrim($apiHost, '/').'/upload');
		$request->setMethod('POST')->setBody($xml);

		//Add generated auth header with signature
		$request->addHeader('Authorization', $this->generateSignatureHeader($xml, $username));

		//Send request
		$client = new HttpClient();
		return $client->sendRequest($request);
	}


	/**
	 * Create the auth header with the username and the signature
	 *
	 * @param string $xml
	 * @param string $username
	 *
	 * @return string
	 * @throws InvalidArgumentException
	 * @throws PKIException
	 * @throws Exception
	 */
	protected function generateSignatureHeader($xml, $username): string {
		$privateKeyFile = SRC_PATH.'/data/test_private_keys/'.$username.'.key';
		if (!file_exists($privateKeyFile)) {
			throw new Exception('Test private key under "data/test_private_keys" folder doesn\'t exist for this user');
		}

		$pkiLib = new PKI();
		$signature = $pkiLib->generateSignature($xml, file_get_contents($privateKeyFile));

		return $pkiLib->authHeaderWithSignature($signature, $username);
	}


}
