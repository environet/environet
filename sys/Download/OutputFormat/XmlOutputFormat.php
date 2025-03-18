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
		$response = new Response();
		$response->addHeader('Content-Type: application/xml');

		$headers = [];
		$response = new Response((new CreateOutputXml())->generateXml($results, $queryMeta, $headers));
		$this->addResponseHeaders($response, $headers);

		return $response;
	}


}
