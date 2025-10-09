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
		$response = new Response((new CreateOutputXml())->generateXml($select, $queryMeta, $headers));
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
