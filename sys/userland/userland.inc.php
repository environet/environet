<?php
/*!
 * File userland.inc.php
 *
 * @author Levente Peres - VIZITERV Environ Kft.
 *
 * Distribution node operation mode - userland operation functions
 *
 * @package Environet\Sys
 */

require_once 'sys/userland/serverside.inc.php';

/**
 * This module will process userland communication protocols
 * - Upload
 * - Download
 * - Query
 * - Administration
 */


/**
 * Takes care of uploading observation data to a remote distribution node
 * Uploading of observation data to the default or selected distribution node.
 *
 * The function takes as a parameter the format ID of the upload, which is essentially
 * the short name of the Class of one of the valid input filters defined in the config file
 * and under the plugins/input directory of the distribution node in question. For example:
 * "wml2" in case of mod_wml2.inc.php (For WaterML 2.0) or "scalar" for the simple Scalar filter
 * defined in the file mod_scalar.inc.php
 *
 *
 * @param string $InputFormatID The name of the class to be used when processing the input data on the DNode side.
 * @param string $ProductFormatID The name of the class to be used when choosing product model definition for processing the input data on the DNode side.
 * @param string $observation The observation data formatted and prepared as a string as per $formatID
 * @param string $node_hostname The hostname of the destination node, if differs from the one set in the config file.
 * @param string $dataproductid This is the unique ID of the dataproduct to where this data should be merged in.
 * @param bool $update Whether to update existing data on server side? (true= yes, false= no)
 *
 * @return string A string response from the Distribution Node containing the transaction ID, or an error string in case of connection failure.
 */
function en_upload($InputFormatID, $ProductFormatID, $observation, $dataproductid, $update = 0, $node_hostname = EN_NEXTNODE_HOST) {

	//! Preparation of request data to the server.
	$postdata = http_build_query(
		array(
			'en_req' => "Upload", //!< Request string - what do we want to do?
			'en_inputformat' => $InputFormatID, //!< The input format plugin the server should apply when interpreting our request
			'en_productformat' => $ProductFormatID, //!< The product format definition module the server should apply when updating the product
			'observation' => base64_encode($observation), //!< The actual observation data package in the format of the selected input plugin
			'api_id' => EN_APIID, //!< Our own API ID, as configured in environet.conf.php
			'dataproduct_id' => $dataproductid, //!< ID of the actual data product to be appended to.
			'signature' => base64_encode(en_pki_signdata($observation)), //!< We sign the observation data package with our private key as configured in environet.conf.php
		)
	);

	//! Setting up connection-specific technical information.
	$opts = array(
		'http' => array(
			'method'  => 'POST', //!< Request method, this should always be POST in this case
			'header'  => 'Content-type: application/x-www-form-urlencoded', //!< Content type in this case should always be this as well.
			'content' => $postdata //!< Finally we attach the actual postdata we prepared above.
		)
	);
	$context  = stream_context_create($opts);
	$result = file_get_contents('https://'.$node_hostname.'/src/index.php', false, $context);
	en_debug("Userland Upload - InputFormat: ".$InputFormatID." - Product format: ".$ProductFormatID." - Host: ".$node_hostname);
	en_debug("Userland Upload - Reply - ".serialize($result));

	//! @todo Error checkings
	return $result;
}


//! Asks the distribution node for the status of a certain transaction ID.
/*! This function requests the status of the transaction by transaction ID.
 *
 * The transaction ID is a string, acquired when calling en_upload function.
 *
 * @param string $transactionID The transaction ID assigned by the Distribution Node, to be queried.
 * @param string $node_hostname The hostname of the destination node, if differs from the one set in the config file.
 *
 * @return An XML string response from the Distribution Node, containing the status of the transaction, or an error string in case of connection failure.
 */
function en_ask_upload_status($transactionID, $node_hostname = false) {
	// Status check code goes here
}


en_debug("USERLAND MODULE LOADED - Not implemented");
