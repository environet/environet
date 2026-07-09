<?php

namespace Environet\Sys\Download\OutputFormat;

use Environet\Sys\General\Db\Query\Select;
use Environet\Sys\General\Response;
use Environet\Sys\Xml\CreateOutputXml;
use Exception;

class XmlOutputFormat extends AbstractOutputFormat {


	/**
	 * Generate the default XML response based on the given results and query metadata.
	 *
	 * @throws Exception
	 */
	public function outputResults(Select $select, array $queryMeta): Response {
		$headers = [];

		$propertyData = $this->getPropertyData($select, $queryMeta, ['observed_property.symbol']);
		$propertySymbols = array_column($propertyData, 'symbol');

		//Pre-load station data (with operator, river, sub-basin joins) so we don't have to bloat the main
		//download query with the same joins just to expose operator name per row.
		//The result is keyed by monitoring point id (via _keyid handled inside getStationData).
		$stationData = $this->getStationData($select, $queryMeta, [
			'operator.name as operator_name',
		]);

		$response = new Response(new CreateOutputXml()->generateXml($select, $queryMeta, $headers, $stationData));
		$this->addResponseHeaders($response, $headers);

		$filename = $this->generateFilename($propertySymbols, $queryMeta);

		$response->addHeader('Content-Type: application/xml')
			->addHeader('Content-Length: ' . $response->getSize())
			->addHeader('Content-Disposition: inline; filename="' . $filename . '.xml"')
			->addHeader('Cache-Control: must-revalidate')
			->addHeader('Pragma: public');

		return $response;
	}


}
