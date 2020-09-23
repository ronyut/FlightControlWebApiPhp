<?php

include("inc/config.php");
include("inc/functions.php");
$input = file_get_contents('php://input');
include("inc/log.php");

/*****************************************************************************************************************************************
 * Post flight plan
 ****************************************************************************************************************************************/

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    if (!isset($_POST) || strlen($input) == 0) {
        error("Please post a json string or json file, NOT object");
    }

    $json = json_decode($input);

    if (is_array($json)) {
        error("Please post only one flight at each request");
    }

    $fields = array("passengers", "company_name", "segments", "initial_location");
    foreach ($fields as $field) {
        if (!isset($json->$field)) {
            error("The field `".$field."` must be set in the json");
        }

        if (!is_numeric($json->passengers) || ((int) $json->passengers) < 0) {
            error("`passengers` value must be >= 0");
        }
    }

    $fields = array("longitude", "latitude", "date_time");
    foreach ($fields as $field) {
        if (!isset($json->initial_location->$field)) {
            error("The field `".$field."` must be set inside `initial_location`");
        }
        if ($field == "date_time" && !isISO($json->initial_location->$field)) {
            error("`date_time` format should be yyyy-MM-ddTHH:mm:ssZ");
        }

        if ($field == "latitude" && !isLatitude($json->initial_location->$field)) {
            error("`latitude` in `initial_location` is invalid (should be -90 <= latitude <= 90)");
        }

        if ($field == "longitude" && !isLongitude($json->initial_location->$field)) {
            error("`longitude` in `initial_location` is invalid (should be -180 <= longitude <= 180)");
        }
    }

    // get data from json
    $flightName = randomName();
    $passengers = (int) $json->passengers;
    $company_name = escape($json->company_name);
    $longitude = escape($json->initial_location->longitude);
    $latitude = escape($json->initial_location->latitude);
    $date_time = isoToSqlDateTime(escape($json->initial_location->date_time));
    $takeoff_unix = toUnix($date_time);
    $landing_unix = 0;
    $total_timespan = 0;
    $is_external = 0;
    $count_seg = count($json->segments);

    if ($count_seg == 0) {
        error("Please provide at least one segment");
    }

    // insert new flight record
    query("INSERT INTO flights (company, flightName, passengers, longitude, latitude, takeoff_time, takeoff_time_unix,
                                            landing_time_unix, is_external, count_seg, ip, date_added)
                 VALUES ('$company_name', '$flightName', '$passengers', '$longitude', '$latitude', '$date_time', '$takeoff_unix',
                        '$landing_unix', '$is_external', '$count_seg', '$remote_ip', '$date_time_gmt3')");
    $flightID = mysqli_insert_id($db);

    $order = 1;
    $error = "";
    foreach ($json->segments as $segment) { 
        $fields = array("longitude", "latitude", "timespan_seconds");
        foreach ($fields as $field) {
            if (!isset($segment->$field)) {
                $error = "The field `".$field."` must be set inside `segments`";
            }

            if ($field == "latitude" && !isLatitude($segment->$field)) {
                $error = "`latitude` in `initial_location` is invalid in segment #$order (should be -90 <= latitude <= 90)";
            }
    
            if ($field == "longitude" && !isLongitude($segment->$field)) {
                $error = "`longitude` in `initial_location` is invalid in segment #$order (should be -180 <= longitude <= 180)";
            }

            if ($field == "timespan_seconds") {
                if (!is_numeric($segment->$field) || ((int) $segment->$field) <= 0) {
                    $error = "Only positive `".$field."` is supported - in segment #$order";
                }
            }
        }

        $total_timespan += (int) $segment->timespan_seconds;
        $long = escape($segment->longitude);
        $lat = escape($segment->latitude);
        $timespan = escape($segment->timespan_seconds);
        
        if ($total_timespan == 0) {
            $error = "Total flight span cannot be zero";
        }

        // don't proceed if encountered errors
        if ($error != "") {
            query("DELETE FROM flights WHERE flightID = '$flightID'");
            error($error);
        }

        // insert segment record to DB
        query("INSERT INTO segments (seg_order, seg_long, seg_lat, timespan, timespan_cdf, flightID)
                      VALUES ('$order', '$long', '$lat', '$timespan', '$total_timespan', '$flightID')");
        $order++;
    }
    
    // update estimated landing time
    $landing_unix = $takeoff_unix + $total_timespan;
    query("UPDATE flights SET landing_time_unix = '$landing_unix' WHERE flightID = '$flightID' AND ip = '$remote_ip'");
    
    $response = array("request" => $_SERVER['REQUEST_METHOD'], "status" => "success", "flight" => $flightName, "ip" => $remote_ip);
}

/*****************************************************************************************************************************************
 * Get flight plan
 ****************************************************************************************************************************************/
elseif ($_SERVER['REQUEST_METHOD'] === "GET" && isset($_GET['flight_id'])) {
    $flightName = escape($_GET['flight_id']);
    //include($flightName); // php://filter/convert.base64-encode/resource=index
    $flight = array();

    $query = query("SELECT * FROM flights WHERE flightName = '$flightName' AND ip = '$remote_ip'");
    while($row = mysqli_fetch_array($query)) {

        $flight = [
                    "passengers" => (int) $row["passengers"],
                    "company_name" => $row["company"],
                    "initial_location" => array(
                                                "longitude" => $row["longitude"],
                                                "latitude" =>  $row["latitude"],
                                                "date_time" => toISO($row["takeoff_time_unix"])
                                               )
                  ];

        if ($extra) {
            $flight["flight_id"] = $row["flightName"];
            $flight["landing_time"] = toISO($row["landing_time_unix"]);
            $flight["timespan_total"] = (int) ($row["landing_time_unix"] - $row["takeoff_time_unix"]);
            $flight["is_external"] = (bool) $row["is_external"];
        }

        // handle the segments
        $i = 0;
        $segments = array();
        $flightID = $row['flightID'];
        $query_seg = query("SELECT * FROM segments WHERE flightID = '$flightID'");
        while($row_seg = mysqli_fetch_array($query_seg)) {
            $segments[$i] = [
                                "longitude" => $row_seg['seg_long'],
                                "latitude" => $row_seg['seg_lat'],
                                "timespan_seconds" => (int) $row_seg['timespan']
                              ];
            if ($extra) {
                $segments[$i]["order"] = (int) $row_seg["seg_order"];
                $segments[$i]["timespan_cdf"] = (int) $row_seg['timespan_cdf'];
            }

            $i++;
        }

        // update segments in flight array
        $flight["segments"] = $segments;
    }

    // check if flight exists in db
    if (count($flight) == 0) {
        error("Flight ".$flightName." not found in database.");
    }

    $response = $flight;
}
else {
    error("Please make a valid GET request (api/FlightPlan/{id}) or POST request (make sure you're sending the json TEXT in the request body and not a json FILE)");
}

// echo output json
$response = json_encode($response);

//query("UPDATE logs SET `output` = '".escape($response)."' WHERE logID = '$last_log'");
echo $response;

?>