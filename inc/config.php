<?php

error_reporting(0);	

// set timezone and date
date_default_timezone_set('UTC');
$date = date("Y-m-d");
$hour = date("H:i:s");
$remote_ip = $_SERVER['REMOTE_ADDR'];
$gmt_offset = 3;
$date_time_gmt3 = date("Y-m-d H:i:s", strtotime('+'.$gmt_offset.' hours')); // to GMT+3
$limit_enabled = false;
$min_query_interval = 1;
$SERVER_NAME = $_SERVER['SERVER_NAME'];

const DB_NAME = "biucs";

/************************************************
	MySQL Connect
************************************************/
$local_names = array("localhost");

$db = 	mysqli_connect("localhost", "root", "") or die(mysqli_error($db));
	mysqli_select_db($db, DB_NAME) or die(mysqli_error($db));
	mysqli_query($db, "SET NAMES 'UTF8'");

error_reporting(E_ALL ^ E_DEPRECATED);


/************************************************
	Headers
************************************************/
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: *');
header("Access-Control-Allow-Headers: *");
header('Content-Type: application/json');

if(isset($_GET['debug'])) {
	error_reporting(E_ALL ^ E_DEPRECATED);
}

?>
