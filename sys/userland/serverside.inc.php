<?php
/*!
 * \file serverside.inc.php
 *
 * \author Levente Peres - VIZITERV Environ Kft.
 * \date
 *
 * Distribution node operation mode - userland operation functions - server side handlers
 *
 * @package Environet
 */

//! Direct call or out-of-band include to this file is denied.
defined('EN_BOOT') or die();


/*! Handles incoming upload requests.
 *
 * This function will evaluate an incoming upload request and queue it if it is authorized and sane.
 *
 * @todo Security, sanity checks, rating and scrutinization
 */
function en_receive_upload() {
	// Handle incoming upload transaction
	
	//! Insert the upload job into the queue.

	//! Connect to PgSQL
	$dbconn = new en_connection();
	
	//! Prepare the query and make the insertions
	$query = 'INSERT INTO en_upload_queue VALUES (nextval(\'en_upload_queue_trid_seq\'::regclass), ?, ?, ?, ?, ?, ?, \'0\') RETURNING trid;';
/*
	$sth = $dbconn->con->prepare($query);
	$sth->execute(array($time,$vaule));
	$result = $sth->fetchAll();
  */
	
	$enqueue = $dbconn->con->prepare($query);
	$enqueue->execute(array(
		$_POST['en_inputformat'],
		$_POST['en_productformat'],
		$_POST['dataproduct_id'],
		$_POST['observation'],
		$_POST['api_id'],
		$_POST['signature']
	));
	$row = $enqueue->fetchAll();
	en_debug("Incoming to queue - APIID: ".$_POST['api_id']." - Product: ".$_POST['dataproduct_id']." - Transaction ID: ".$row[0]['trid']);
	
	echo $row[0]['trid'];
	
	//!@todo AMONG OTHERS, CHECK IF SELECTED DATAPRODUCT SUPPORTS THE UPLOAD FORMAT ID
}


/*! Handles incoming download requests.
 *
 * This function will evaluate an incoming download request and serve it if it is authorized and sane.
 *
 */
function en_send_transdata() {
	// Handle sending of data
}
