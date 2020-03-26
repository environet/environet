<?php 
/*!
 * \file cron.php
 * 
 * \author Levente Peres - VIZITERV Environ Kft.
 * \date
 * 
 * Background tasks broker
 *
 * This file needs to be called for background tasks to be ran.
 * 
 * It has to be called periodically, preferably every minute or so.
 *
 * @package Environet
 */


if ( !empty($_POST) || defined('DOING_AJAX') || defined('DOING_CRON') || defined ('DOING_INSTALL')) {
    die();
}

define('DOING_CRON',true);

require_once 'index.php';

en_debug("CRONTAB CONCLUDED");

?>