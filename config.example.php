<?php

require_once 'rb.php'; // https://www.redbeanphp.com/
require_once 'functions.php';
ini_set('memory_limit', '2G');

// Database
define('DB_HOST', '');
define('DB_USER', '');
define('DB_PASS', '');
define('DB_NAME', '');
define('DB_PORT', '3306');

// Slack API credentials
define('SLACK_API_TOKEN', '');

R::setup('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
