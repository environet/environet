<?php


namespace Environet\Sys\Admin\Pages\UploadData;

use Environet\Sys\Admin\Pages\BasePage;
use Environet\Sys\Config;
use Environet\Sys\General\Db\HydroMonitoringPointQueries;
use Environet\Sys\General\Db\MeteoMonitoringPointQueries;
use Environet\Sys\General\Db\Query\Query;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Db\UserQueries;
use Environet\Sys\General\Exceptions\HttpBadRequestException;
use Environet\Sys\General\Exceptions\PKIException;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\General\HttpClient\Exceptions\HttpClientException;
use Environet\Sys\General\HttpClient\HttpClient;
use Environet\Sys\General\HttpClient\Request;
use Environet\Sys\General\PKI;
use Environet\Sys\General\SysIdentity;
use Environet\Sys\Xml\CreateInputXml;
use Environet\Sys\Xml\Exceptions\CreateInputXmlException;
use Environet\Sys\Xml\Model\InputXmlData;
use Environet\Sys\Xml\Model\InputXmlPropertyData;
use Exception;
use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Class AbstractUploadDataPage
 *
 * Abstract page for csv upload pages
 *
 * @package Environet\Sys\Admin\Pages\UploadData
 * @author  SRG Group <dev@srg.hu>
 */
abstract class AbstractUploadDataPage extends BasePage {


	/**
	 * Get directory path where upload files will be stored before processing
	 *
	 * @return string
	 */
	abstract protected function getFileDir(): string;


	/**
	 * Get file input field's name
	 *
	 * @return string
	 */
	abstract protected function getFileInputName(): string;


	/**
	 * Map the CSV file, parse monitoring point id, and values for multiple properties.
	 *
	 * @param resource $fileHandle File handle of csv file
	 *
	 * @return array Array of processed data. First item must be the monitoring point id, second is the property data, grouped by property symbol
	 */
	abstract protected function mapCsv($fileHandle): array;


	/**
	 * Handle post upload request. Check CSRF token, call processData function, and store flash messages
	 *
	 * @throws HttpBadRequestException
	 */
	protected function handlePost() {
		// Posted form, check data, and send it to the API
		if (!$this->checkCsrf()) {
			// CSRF error
			throw new HttpBadRequestException();
		}
		try {
			// Send the data with a http client, and store the response body in a variable
			$this->processData();
		} catch (HttpClientException | CreateInputXmlException $e) {
			// Store error response of the request in $error var
			$this->addMessage($e->getMessage(), self::MESSAGE_ERROR);
		} catch (Exception $e) {
			// Store error response of the request in $error var
			$this->addMessage($e->getMessage(), self::MESSAGE_ERROR);
		}
	}


	/**
	 * Create an input-XML based on the form's data, and send it to the API with the HTTP client
	 *
	 * @return void
	 * @throws Exception
	 * @see  CreateInputXml
	 */
	protected function processData() {
		//Check if $_FILES array is valud
		if (empty($_FILES) || empty($_FILES['csv']) || !is_array($_FILES['csv']['name']) || empty(array_filter($_FILES['csv']['name']))) {
			throw new Exception('No file was uploaded');
		}

		//Get instance specific properties
		$dir = $this->getFileDir();
		$fileInputName = $this->getFileInputName();

		//Create directory if not exists
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		//Move uploaded files to the permanent directory. Collect files to an array, with original file names as keys.
		//These original file names will be used in error and success messages to show which file was problematic
		$files = [];
		foreach ($_FILES[$fileInputName]['name'] as $fileKey => $originalName) {
			$storedFile = $dir . '/' . time() . '_' . uniqid() . '_' . $fileKey . '.csv';
			move_uploaded_file($_FILES[$fileInputName]['tmp_name'][$fileKey], $storedFile);
			$files[$originalName] = $storedFile;
		}

		//Iterate over files, and try to process it.
		foreach ($files as $originalFileName => $file) {
			try {
				$this->processFile($file);
				$this->addMessage(sprintf("File processed and has been sent successfully: %s", $originalFileName), self::MESSAGE_SUCCESS);
			} catch (Exception $exception) {
				$this->addMessage(sprintf("Error during processing file %s: %s", $originalFileName, $exception->getMessage()), self::MESSAGE_ERROR);
			}
		}
	}


	/**
	 * Process a single csv file, map with child instance, and send it to API endpoint
	 *
	 * @param string $file File path
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function processFile(string $file) {
		//Try to open file
		if (!(file_exists($file) && ($fileHandle = fopen($file, 'r')) !== false)) {
			throw new Exception('An error occured: File doesn\'t exist, or can\'t open: ' . $file);
		}

		//Map CSV file
		[$mpointId, $propertiesData] = $this->mapCsv($fileHandle);

		if (!$mpointId) {
			//Mpoint not found
			throw new Exception("Monitoring point id not found in csv");
		}

		if (empty(array_filter($propertiesData))) {
			//Values are empty, or invalid
			throw new Exception("No valid data found in file");
		}


		//Find operator ids of user
		$operatorIds = [];
		if (($operators = UserQueries::getOperatorsOfUser($this->request->getIdentity()->getId()))) {
			$operatorIds = array_map(function ($operator) {
				return $operator['id'];
			}, $operators);
		}

		//Find monitoring points with ID and for operator ids of user
		$point = $this->getMonitoringPoint($mpointId, $operatorIds);
		if (!$point) {
			throw new Exception("Monitoring point with ID $mpointId not found in database");
		}

		// Create root XML
		$creator = new CreateInputXml();

		//Iterate over mapped properties, parse time, values, and collect it to an array which will be compatible with InputXmlPropertyData
		$propertyXmls = [];
		foreach ($propertiesData as $propertySymbol => $propertyData) {
			//Filter out rows with empty time or value
			$propertyData = array_values(array_filter($propertyData, function ($datum) {
				return is_array($datum) && !empty($datum['time']) && isset($datum['value']);
			}));
			//Create SimpleXmlElement for this property
			$propertyXmls[] = new InputXmlPropertyData($propertySymbol, $propertyData);
		}
		//Generate whole xml content
		$xml = $creator->generateXml(new InputXmlData($mpointId, $propertyXmls))->asXML();

		// Create a request
		$apiHost = Config::getInstance()->getDatanodeDistHost();
		$apiHost = preg_match('/^https?:\/\//', $apiHost) ? $apiHost : 'https://' . $apiHost;
		$request = new Request(rtrim($apiHost, '/') . '/upload');
		$request->setMethod('POST')->setBody($xml);

		// Add generated auth header with signature
		$request->addHeader('Authorization', $this->generateSignatureHeader($xml, SYS_USERNAME));

		// Send request
		$client = new HttpClient();
		$response = $client->sendRequest($request);

		if ($response->getStatusCode() !== 200) {
			//Some error returned from upload API
			if (($xml = simplexml_load_string($response->getBody())) !== false &&
				($messages = $xml->xpath('/environet:ErrorResponse/environet:Error/environet:ErrorMessage'))
			) {
				//Valid XML error, parse error messages from error XML
				$messages = array_map(function (SimpleXMLElement $element) {
					return (string) $element;
				}, $messages);
				throw new Exception("Error when sending data to upload api endpoint: " . (implode(', ', $messages) . ""));
			} else {
				//Not a valid XML error, unknown
				throw new Exception("Unknown error while sending data to upload api endpoint");
			}
		}
	}


	/**
	 * Find a hyro or meteo point by ID and by operator ids. So if a point not allowed for user, response will be empty.
	 *
	 * @param string $mPointId
	 * @param array  $operatorIds
	 *
	 * @return array|bool|int|null
	 * @throws QueryException
	 */
	protected function getMonitoringPoint($mPointId, array $operatorIds) {
		//Find hydro point
		$hydro = (new Select())
			->select(HydroMonitoringPointQueries::$tableName . '.*')
			->from(HydroMonitoringPointQueries::$tableName)
			->where(HydroMonitoringPointQueries::$tableName . '.ncd_wgst = :id')
			->addParameter('id', $mPointId)
			->whereIn(HydroMonitoringPointQueries::$tableName . '.operatorid', $operatorIds, 'opid')
			->run(Query::FETCH_FIRST);
		if ($hydro) {
			return $hydro;
		}
		//Find meteo point
		$meteo = (new Select())
			->select(MeteoMonitoringPointQueries::$tableName . '.*')
			->from(MeteoMonitoringPointQueries::$tableName)
			->where(MeteoMonitoringPointQueries::$tableName . '.ncd_pst = :id')
			->addParameter('id', $mPointId)
			->whereIn(MeteoMonitoringPointQueries::$tableName . '.operatorid', $operatorIds, 'opid')
			->run(Query::FETCH_FIRST);
		if ($meteo) {
			return $meteo;
		}
	}


	/**
	 * Create the auth header with the username and the signature. For username and interval, SYS user will be used with own keypair.
	 * It this keypair doesn't exist, create it. Thes keypair will be used in upload api process too.
	 *
	 * @param string $xml
	 * @param string $username
	 *
	 * @return string
	 * @throws InvalidArgumentException
	 * @throws PKIException
	 * @throws Exception
	 * @uses \Environet\Sys\General\PKI::generateSignature()
	 * @uses \Environet\Sys\General\PKI::generateKeyPair()
	 * @uses \Environet\Sys\General\PKI::authHeaderWithSignature()
	 */
	protected function generateSignatureHeader($xml, $username): string {
		$pkiLib = new PKI();

		//Key file paths
		$privateKeyFile = SysIdentity::getSysPrivateKeyFile();
		$publicKeyFile = SysIdentity::getSysPublicKeyFile();
		if (!file_exists($privateKeyFile)) {
			//Create new keypair
			[$publicKey, $privateKey] = $pkiLib->generateKeyPair();

			// Write files
			file_put_contents($privateKeyFile, $privateKey);
			file_put_contents($publicKeyFile, $publicKey);
		}

		//Generate signature from xml content and return auth header signature
		$signature = $pkiLib->generateSignature(md5($xml), file_get_contents($privateKeyFile));

		return $pkiLib->authHeaderWithSignature($signature, $username);
	}


}
