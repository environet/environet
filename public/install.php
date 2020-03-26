<?php 
/*!
 * \file install.php
 * 
 * \author Levente Peres - VIZITERV Environ Kft.
 * \date
 * 
 * Installation initiator script
 *
 * This script sets the environment to start a fresh installation or update of the system.
 * 
 * TODO: Set different operation modes for table structure update, fresh install and version upgrade
 *
 * @package Environet
 */

if ( !empty($_POST) || defined('DOING_AJAX') || defined('DOING_CRON') || defined ('DOING_INSTALL')) {
    die();
}

//! Tell Environet we are doing an install
define( 'DOING_INSTALL', true );

require_once 'index.php';

en_debug("INSTALL NOT IMPLEMENTED");
?>