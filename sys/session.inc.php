<?php

/**
 * File session.inc.php
 *
 * @author Adam Balint <adam.balint@srg.hu>
 *
 * Init session
 *
 * @package Environet
 */


ini_set('session.use_cookies', 1);
session_set_cookie_params(86400, '/', $_SERVER['SERVER_NAME'], isset($_SERVER['HTTPS']), true);

session_start();
session_register_shutdown();
//@TODO securing session, regenerate id, etc...