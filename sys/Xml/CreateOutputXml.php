<?php

namespace Environet\Sys\Xml;

use DOMDocument;
use DOMException;
use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Exceptions\QueryException;
use Environet\Sys\Xml\Model\OutputXmlObservationMember;
use PDO;
use SimpleXMLElement;

/**
 * Create an XML file base on the WaterML schema, which will be served in the download API.
 */
class CreateOutputXml {


	/** @var string Path to a temporary directory for storing intermediate files. */
	protected string $tmpDirPath;


	public function __construct() {
		$tmpDirPath = SRC_PATH . '/data/tmp/output' . time() . '_' . uniqid();
		if (!is_dir($tmpDirPath)) {
			mkdir($tmpDirPath, 0755, true);
		}
		$this->tmpDirPath = $tmpDirPath;
	}


	/**
	 * Generate output XML.
	 * Creates a temporary file for each monitoring point - observation property combination, writes the values to it,
	 * then creates the final XML by combining the metadata and the observation members.
	 *
	 * @return SimpleXMLElement
	 * @throws DOMException
	 * @throws QueryException
	 */
	public function generateXml(Select $select, array $queryMeta, array &$headers): string {
		//Create the PDO statement for fetching the values
		$stmt = $select->createStatement();

		//Iterate through the values, create temporary files for each monitoring point - property combination
		$memberValueFiles = [];
		while ($valueRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$memberKey = "{$valueRow['mpoint_id']}_{$valueRow['property_id']}";
			$memberValuesFile = $this->tmpDirPath . "/member_{$memberKey}_values.xml";
			if (!isset($memberValueFiles[$memberValuesFile])) {
				//Create new observation member handler, and store it in the array with the file path as key
				$memberValueFiles[$memberValuesFile] = new OutputXmlObservationMember($queryMeta);
			}

			//Write the value row to the appropriate temporary file
			file_put_contents(
				$memberValuesFile,
				$this->sanitizeXmlPart($memberValueFiles[$memberValuesFile]->renderValue($valueRow)),
				FILE_APPEND | LOCK_EX
			);
		}

		//Now iterate through the temporary files, render the observation member metadata, and replace the <tmp:values/> and <tmp:result/> tags
		//These tmp tags are used as placeholders for easier string replacement
		foreach ($memberValueFiles as $memberValueFile => $observationMember) {
			$renderedMeasurementResult = $this->sanitizeXmlPart($observationMember->renderMeasurementResult());
			$renderedMeta = preg_replace(
				'/<tmp:result\/>/i',
				$renderedMeasurementResult,
				$this->sanitizeXmlPart($observationMember->renderObservationMember())
			);
			file_put_contents(
				$memberValueFile,
				preg_replace('/<tmp:values\/>/i', file_get_contents($memberValueFile), $renderedMeta)
			);
		}

		//Create the root document, and insert the metadata, parameters
		$rootDoc = $this->createRootDoc();


		//Render the empty root document to string, split it at the <tmp:collection/> tag. Between these two parts we will insert any other parts
		$rootDocXml = $rootDoc->saveXML();
		$rootDocBoundaries = preg_split('/(<tmp:collection\/>)/uim', $rootDocXml);

		//Create the final file by combining the parts
		$finalFile = $this->tmpDirPath . '/final_output.xml';
		$f = fopen($finalFile, 'w');
		fwrite($f, $rootDocBoundaries[0]);

		//Render metadata part and write it to the file
		fwrite($f, $this->sanitizeXmlPart($this->renderMeta()));

		//Render parameters part and write it to the file
		fwrite($f, $this->sanitizeXmlPart($this->renderParameters($queryMeta, $headers)));

		//Write all the observation members to the file
		foreach (array_keys($memberValueFiles) as $memberValueFile) {
			fwrite($f, file_get_contents($memberValueFile));
		}

		//Write the end of the root document
		fwrite($f, $rootDocBoundaries[1]);
		fclose($f);

		//Get the contents of the final file, remove the temporary directory and return the contents
		$fileContents = file_get_contents($finalFile);

		rrmdir($this->tmpDirPath);

		return $fileContents;
	}


	/**
	 * Render the metadata part of the XML.
	 *
	 * @throws DOMException
	 */
	protected function renderMeta(): string {
		$docMetadata = new DOMDocument('1.0', 'UTF-8');
		$docMetadata->appendChild($meta = $docMetadata->createElement('wml2:metadata'));

		$meta->appendChild($docMeta = $docMetadata->createElement('wml2:DocumentMetadata'));

		$docMeta->appendChild($docMetadata->createElement('wml2:generationDate', dateToISO('now')));

		$version = $docMetadata->createElement('wml2:version');
		$version->setAttribute('xlink:href', 'http://www.opengis.net/waterml/2.0');
		$version->setAttribute('xlink:title', 'WaterML 2.0');
		$docMeta->appendChild($version);

		$docMeta->appendChild($docMetadata->createElement('wml2:generationSystem', 'HyMeDES EnviroNet'));

		return $docMetadata->saveXML();
	}


	/**
	 * Render the parameters part of the XML.
	 *
	 * @throws DOMException
	 */
	protected function renderParameters(array $queryMeta, array &$headers = []): string {
		$docParameter = new DOMDocument('1.0', 'UTF-8');
		if (isset($queryMeta['intervalLimited']) && $queryMeta['intervalLimited'] === true) {
			$message = 'Start of time series was automatically limited to available data';
			$docParameter->appendChild($parameter = $docParameter->createElement('wml2:parameter'));
			$parameter->appendChild($parameterNV = $docParameter->createElement('om:NamedValue'));

			$parameterName = $docParameter->createElement('om:name');
			$parameterName->setAttribute('xlink:title', 'message');
			$parameterNV->appendChild($parameterName);

			$parameterNV->appendChild($docParameter->createElement('om:value', $message));

			$headers['X-Environet-Note'] = $message;
		}

		return $docParameter->saveXML();
	}


	/** @noinspection HttpUrlsUsage */
	protected function createRootDoc(): DOMDocument {
		$rootDoc = new DOMDocument('1.0', 'UTF-8');
		$rootDoc->appendChild($collection = $rootDoc->createElement('wml2:Collection'));
		$collection->setAttribute('xmlns:wml2', "http://www.opengis.net/waterml/2.0");
		$collection->setAttribute('xmlns:gml', "http://www.opengis.net/gml/3.2");
		$collection->setAttribute('xmlns:xsi', "http://www.w3.org/2001/XMLSchema-instance");
		$collection->setAttribute('xmlns:om', "http://www.opengis.net/om/2.0");
		$collection->setAttribute('xmlns:sa', "http://www.opengis.net/sampling/2.0");
		$collection->setAttribute('xmlns:sams', "http://www.opengis.net/samplingSpatial/2.0");
		$collection->setAttribute('xmlns:xlink', "http://www.w3.org/1999/xlink");
		$collection->setAttribute(
			'xsi:schemaLocation',
			"http://www.opengis.net/waterml/2.0 " .
			"http://schemas.opengis.net/waterml/2.0/waterml2.xsd " .
			"http://www.opengis.net/gml/3.2 " .
			"http://schemas.opengis.net/gml/3.2.1/gml.xsd " .
			"http://www.opengis.net/om/2.0 " .
			"http://schemas.opengis.net/om/2.0/observation.xsd " .
			"http://www.opengis.net/sampling/2.0 " .
			"http://schemas.opengis.net/sampling/2.0/samplingFeature.xsd " .
			"http://www.opengis.net/samplingSpatial/2.0 " .
			"http://schemas.opengis.net/samplingSpatial/2.0/spatialSamplingFeature.xsd"
		);
		$collection->appendChild($rootDoc->createElement('tmp:collection'));

		return $rootDoc;
	}


	protected function sanitizeXmlPart(string $xmlString): string {
		return preg_replace('/<\?xml.*?\?>\n?/uim', '', $xmlString);
	}


}
