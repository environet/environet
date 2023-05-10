<?php


namespace Environet\Sys\Admin\Pages\UploadData;

use DateTimeZone;
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
use Environet\Sys\General\HttpClient\Response;
use Environet\Sys\General\PKI;
use Environet\Sys\General\SysIdentity;
use Environet\Sys\Upload\Statistics;
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
	abstract protected function getCsvFileDir(): string;


	/**
	 * Get directory path where xml files will be stored before sending
	 *
	 * @return string
	 */
	abstract protected function getXmlFileDir(): string;


	/**
	 * Get file input field's name
	 *
	 * @return string
	 */
	abstract protected function getFileInputName(): string;


	/**
	 * Get permission of upload-to-all functionality
	 *
	 * @return string
	 */
	abstract protected function getUploadAllPermission(): string;


	/**
	 * Handle post upload request with dry-run, to get statistics.
	 * Check CSRF token, call preProcessData function, and store flash messages
	 *
	 * @throws HttpBadRequestException
	 */
	protected function handleStatistics(): array {
		// Posted form, check data, and send it to the API
		if (!$this->checkCsrf()) {
			// CSRF error
			throw new HttpBadRequestException();
		}
		try {
			// Send the data with a http client, and store the response body in a variable
			return $this->preProcessData();
		} catch (HttpClientException|CreateInputXmlException $e) {
			// Store error response of the request in $error var
			$this->addMessage($e->getMessage(), self::MESSAGE_ERROR);
		} catch (Exception $e) {
			// Store error response of the request in $error var
			$this->addMessage($e->getMessage(), self::MESSAGE_ERROR);
		}

		return [];
	}


	/**
	 * Pre-process data with sending the conbverted xml files to the statistics endpoint of distribution node.
	 * After getting statistic responses for each file, this statistics or errors can be displayed on the confirmation page
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function preProcessData(): array {
		$selectedTimezoneOption = $this->request->getCleanData()['timezone_selector'] ?? null;

		//Store uploaded csv files
		$csvFiles = $this->storeFiles();

		//Convert uploaded csv files to xml, and store it
		$xmlFiles = $this->convertFilesToXml($csvFiles, $selectedTimezoneOption);

		//Process each xml files
		$fileResponses = [];
		foreach ($xmlFiles as $originalFileName => $xmlFile) {
			if (is_array($xmlFile)) {
				//Xml file is already an array of error messages, not an xml path
				$fileResponses[$originalFileName] = $xmlFile;
				continue;
			}

			//Do upload with statistics
			$response = $this->makeRequest('/upload/statistics', $xmlFile);

			if ($response->getStatusCode() === 200) {
				//Successful request, build statistics from XML response
				$fileResponses[$originalFileName] = Statistics::fromXml(new SimpleXMLElement($response->getBody()))->setInputXmlFile($xmlFile);
			} else {
				//Some error returned from upload API
				if (($xml = simplexml_load_string($response->getBody())) !== false &&
					($messages = $xml->xpath('/environet:ErrorResponse/environet:Error/environet:ErrorMessage'))
				) {
					//Valid XML error, parse error messages from error XML
					$messages = array_map(function (SimpleXMLElement $element) {
						return (string) $element;
					}, $messages);
					$fileResponses[$originalFileName] = $messages;
				} else {
					//Not a valid XML error, unknown
					throw new Exception("Unknown error while sending data to upload api endpoint");
				}
			}
		}

		return $fileResponses;
	}


	/**
	 * Send the pre-processed and stored XMLs to the upload endpoint
	 *
	 * @param array $xmlFiles
	 *
	 * @return void
	 * @throws HttpClientException
	 * @throws PKIException
	 * @see  CreateInputXml
	 */
	protected function handleSend(array $xmlFiles) {
		//Iterate over files, and send it
		foreach ($xmlFiles['xml'] ?? [] as $key => $xmlFile) {
			$originalFileName = $xmlFiles['original'][$key] ?? basename($xmlFile);
			$response = $this->makeRequest('/upload', $xmlFile);

			if ($response->getStatusCode() === 200) {
				$this->addMessage(sprintf("File processed and has been sent successfully: %s", $originalFileName), self::MESSAGE_SUCCESS);
			} else {
				//Some error returned from upload API
				if (($xml = simplexml_load_string($response->getBody())) !== false &&
					($messages = $xml->xpath('/environet:ErrorResponse/environet:Error/environet:ErrorMessage'))
				) {
					//Valid XML error, parse error messages from error XML
					$messages = array_map(function (SimpleXMLElement $element) {
						return (string) $element;
					}, $messages);
					$message = 'Error when sending data to upload api endpoint: ' . implode(', ', $messages);
				} else {
					//Not a valid XML error, unknown
					$message = 'Unknown error while sending data to upload api endpoint';
				}
				$this->addMessage(sprintf("Error during processing file %s: %s", $originalFileName, $message), self::MESSAGE_ERROR);
			}
		}
	}


	/**
	 * Store uploaded csv files in data folder, and validate the sizer of it
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function storeFiles(): array {
		//Check if $_FILES array is valid
		if (empty($_FILES) || empty($_FILES['csv']) || !is_array($_FILES['csv']['name']) || empty(array_filter($_FILES['csv']['name']))) {
			throw new Exception('No file was uploaded');
		}

		//Get instance specific properties
		$dir = $this->getCsvFileDir();
		$fileInputName = $this->getFileInputName();

		//Create directory if not exists
		if (!is_dir($dir)) {
			mkdir($dir, 0775, true);
		}

		//Move uploaded files to the permanent directory. Collect files to an array, with original file names as keys.
		//These original file names will be used in error and success messages to show which file was problematic
		$files = [];
		$size = 0;
		$filenames = [];
		$i = 0;
		foreach ($_FILES[$fileInputName]['name'] as $fileKey => $originalName) {
			$storedFileName = $this->generateFilename($originalName, $filenames, ++ $i);
			$storedFile = $dir . '/' . $storedFileName;

			move_uploaded_file($_FILES[$fileInputName]['tmp_name'][$fileKey], $storedFile);
			$size += filesize($storedFile);
			$files[$originalName] = $storedFile;
		}

		if ($size > Config::getInstance()->getUploadMaxSizeInBytes()) {
			throw new Exception('Uploaded files are too big');
		}

		return $files;
	}


	/**
	 * Convert uploaded csv files to XMLs
	 *
	 * @param array  $files
	 * @param string $selectedTimezoneOption
	 *
	 * @return array
	 */
	protected function convertFilesToXml(array $files, string $selectedTimezoneOption): array {
		//Iterate over files, and convert each to xml
		$xmlFiles = [];
		foreach ($files as $originalFileName => $file) {
			try {
				$xmlFiles[$originalFileName] = $this->csvToXml($file, $selectedTimezoneOption);
			} catch (Exception $exception) {
				$xmlFiles[$originalFileName] = [
					sprintf("Error during converting file %s: %s", $originalFileName, $exception->getMessage())
				];
			}
		}

		return $xmlFiles;
	}


	/**
	 * Convert a csv file to xml
	 *
	 * @param string $file
	 *
	 * @return string
	 * @throws CreateInputXmlException
	 * @throws QueryException
	 */
	protected function csvToXml(string $file, string $selectedTimezoneOption): string {
		//Try to open file
		if (!(file_exists($file) && ($fileHandle = fopen($file, 'r')) !== false)) {
			throw new Exception('An error occured: File doesn\'t exist, or can\'t open: ' . $file);
		}

		//Map CSV file
		[$mpointId, $propertiesData] = $this->mapCsv($fileHandle, $selectedTimezoneOption);

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

		//Create directory if not exists
		if (!is_dir($this->getXmlFileDir())) {
			mkdir($this->getXmlFileDir(), 0775, true);
		}

		$xmlFilePath = $this->getXmlFileDir() . '/' . basename($file, '.csv') . '.xml';
		file_put_contents($xmlFilePath, $xml);

		unset($xml);

		return $xmlFilePath;
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
		$hydroSelect = (new Select())
			->select(HydroMonitoringPointQueries::$tableName . '.*')
			->from(HydroMonitoringPointQueries::$tableName)
			->where(HydroMonitoringPointQueries::$tableName . '.ncd_wgst = :id')
			->addParameter('id', $mPointId);
		if (!$this->request->getIdentity()->hasPermissions([$this->getUploadAllPermission()])) {
			$hydroSelect->whereIn(HydroMonitoringPointQueries::$tableName . '.operatorid', $operatorIds, 'opid');
		}
		if (($hydro = $hydroSelect->run(Query::FETCH_FIRST))) {
			return $hydro;
		}
		//Find meteo point
		$meteoSelect = (new Select())
			->select(MeteoMonitoringPointQueries::$tableName . '.*')
			->from(MeteoMonitoringPointQueries::$tableName)
			->where(MeteoMonitoringPointQueries::$tableName . '.ncd_pst = :id')
			->addParameter('id', $mPointId);
		if (!$this->request->getIdentity()->hasPermissions([$this->getUploadAllPermission()])) {
			$meteoSelect->whereIn(MeteoMonitoringPointQueries::$tableName . '.operatorid', $operatorIds, 'opid');
		}
		if (($meteo = $meteoSelect->run(Query::FETCH_FIRST))) {
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

			if (!is_dir(SysIdentity::SYS_KEY_DIR)) {
				mkdir(SysIdentity::SYS_KEY_DIR, 0755, true);
			}

			// Write files
			file_put_contents($privateKeyFile, $privateKey);
			file_put_contents($publicKeyFile, $publicKey);
		}

		//Generate signature from xml content and return auth header signature
		$signature = $pkiLib->generateSignature(md5($xml), file_get_contents($privateKeyFile));

		return $pkiLib->authHeaderWithSignature($signature, $username);
	}


	/**
	 * Map the CSV file, parse monitoring point id, and values for multiple properties.
	 *
	 * @param resource $fileHandle File handle of csv file
	 * @param string   $selectedTimezoneOption
	 *
	 * @return array Array of processed data. First item must be the monitoring point id, second is the property data, grouped by property symbol
	 * @throws Exception
	 */
	protected function mapCsv($fileHandle, string $selectedTimezoneOption): array {
		$mpointId = null;
		$properties = [];
		$propertiesData = [];
		$rowIndex = 0;
		$inputTimezone = new DateTimeZone($selectedTimezoneOption);
		$toTimezone = new DateTimeZone('UTC');
		while (($row = fgetcsv($fileHandle, 10000)) !== false) {
			$rowIndex ++;
			if ($rowIndex === 1 && !empty($row[1])) {
				//Get mpoint id from first row
				$mpointId = $row[1];
			}
			if ($rowIndex === 2) {
				//Get properties from row 2. First column will be the date, it's not a property
				$properties = array_slice($row, 1, null, true);
				$propertiesData = array_fill_keys($properties, []);
			}
			if ($rowIndex > 2) {
				//Data rows with dates and values for each property
				foreach ($properties as $propertyKey => $property) {
					if (!(!empty($row[0]) && ($dateTime = date_create($row[0], $inputTimezone)))) {
						continue;
					}
					$dateTime->setTimezone($toTimezone);
					$propertiesData[$property][] = [
						'time'  => $dateTime->format('c'),
						'value' => $row[$propertyKey] ? floatval($row[$propertyKey]) : null
					];
					unset($dateTime);
				}
			}
		}

		return [$mpointId, $propertiesData];
	}


	/**
	 * Make an upload, or statistic request
	 *
	 * @param string $path
	 * @param string $bodyFile
	 *
	 * @return Response
	 * @throws HttpClientException
	 * @throws PKIException
	 */
	protected function makeRequest(string $path, string $bodyFile): Response {
		// Create a request
		$apiHost = Config::getInstance()->getDatanodeDistHost();
		$apiHost = preg_match('/^https?:\/\//', $apiHost) ? $apiHost : 'https://' . $apiHost;
		$request = new Request(rtrim($apiHost, '/') . $path);
		$request->setBody(file_get_contents($bodyFile));
		$request->setMethod('POST');

		// Add generated auth header with signature
		$request->addHeader('Authorization', $this->generateSignatureHeader($request->getBody(), SYS_USERNAME));

		// Send request
		$client = new HttpClient();

		return $client->sendRequest($request);
	}


	/**
	 * Generate filename for uploaded files
	 *
	 * @param string $originalName
	 * @param array  $filenames
	 * @param int    $i
	 *
	 * @return string
	 */
	protected function generateFilename(string $originalName, array &$filenames, int $i): string {
		//Remove extension
		$originalNameBase = preg_replace('/^(.*)\.[^\.]+$/', '$1', $originalName);

		//Generate filename
		$storedFile = implode('_', [
			date('Ymd-His'),
			($this->request->getIdentity()->getId() ?? 0),
			preg_replace('/[^a-zA-Z0-9-]/', '', $originalNameBase)
		]);

		//If uploaded filenames are the same, add a suffix to it.
		if (in_array($storedFile, $filenames)) {
			$storedFile .= '_' . $i;
		}
		$filenames[] = $storedFile;

		return $storedFile . '.csv';
	}


}
