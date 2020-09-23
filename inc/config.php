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
    const HOST_MAP = array(
            "rony1" => "3451754",
            "rony3" => "3451771",
            "rony6" => "3451776",
            "rony7" => "3456555",
            "rony9" => "3456571",
            "rony10" => "3456576"
        );

	/************************************************
		MySQL Connect
	************************************************/
	$local_names = array("localhost");

    $db = 	mysqli_connect("localhost", "root", "") or die(mysqli_error($db));
    mysqli_select_db($db, "ap2") or die(mysqli_error($db));
    
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