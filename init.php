<?php

ini_set('memory_limit', '2G');

require_once 'rb.php'; // https://www.redbeanphp.com/
require_once 'functions.php';
require_once 'config.php';

R::setup('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);

