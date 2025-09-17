<?php

namespace Environet\Sys\Download\OutputFormat;

use Environet\Sys\General\Response;
use Environet\Sys\Xml\CreateOutputXml;
use Exception;

/**
 * Class XmlOutputFormat
 */
class XmlOutputFormat extends AbstractOutputFormat {


	/**
	 * Generate the default XML response based on the given results and query metadata.
	 *
	 * @param array $results
	 * @param array $queryMeta
	 *
	 * @return Response
	 * @throws Exception
	 */
	public function outputResults(array $results, array $queryMeta): Response {
		$headers = [];
		$response = new Response((new CreateOutputXml())->generateXml($results, $queryMeta, $headers));
		$this->addResponseHeaders($response, $headers);

		$filename = $this->generateFilename([], $queryMeta);

		$response->addHeader('Content-Type: application/xml')
			->addHeader('Content-Length: ' . $response->getSize())
			->addHeader('Content-Disposition: inline; filename="' . $filename . '.xlsx"')
			->addHeader('Cache-Control: must-revalidate')
			->addHeader('Pragma: public');

		return $response;
	}


}
