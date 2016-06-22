<?php
require_once('config.php');

session_start();
setlocale(LC_ALL, 'nl_NL');
date_default_timezone_set('Europe/Amsterdam');

require_once ('includes/MysqliDb.php');
require_once('includes/class.lotto.php');
require_once('includes/class.players.php');

$db = new MysqliDb (DB_HOST, DB_USER, DB_PASS, DB_NAME);
$players = new players();
