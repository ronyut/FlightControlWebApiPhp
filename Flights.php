<?php

include("inc/config.php");
include("inc/functions.php");
include("inc/log.php");

/*
 * Get flights
 */
if ($_SERVER['REQUEST_METHOD'] === "GET" && isset($_GET['relative_to'])) {

    if (strpos($_GET['relative_to'], '<') !== false || strpos($_GET['relative_to'], '>') !== false) {
        error("Remove `<` and `>` from `relative_to`");
    }

    if (!isISO($_GET['relative_to'])) {
        error("`relative_to` format should be yyyy-MM-ddTHH:mm:ssZ (without `<` and `>`)");
    }

    $relative_to = escape($_GET['relative_to']);
    $relative_to = toUnix($relative_to);
    
    $i = 0;
    $flights;

    $sql_only_internal = "";
    if (!isset($_GET['sync_all'])) {
        $sql_only_internal = " AND is_external = '0'";
    }
    
    $flights = array();
    $query = query("SELECT * FROM flights WHERE ip = '$remote_ip' AND takeoff_time_unix <= '$relative_to' AND landing_time_unix >= '$relative_to' $sql_only_internal");
    while($row = mysqli_fetch_array($query)) {
        $time_passed = $relative_to - $row["takeoff_time_unix"];
        $calcedData = calcFlight($time_passed, $row["flightID"]);
        $flights[$i] = [
                            "flight_id" => $row["flightName"],
                            "longitude" => $calcedData["coords"]["longitude"],
                            "latitude" => $calcedData["coords"]["latitude"],
                            "passengers" => (int) $row["passengers"],
                            "company_name" => $row["company"],
                            "date_time" => toISO($row["takeoff_time_unix"]),
                            "is_external" => (bool) $row["is_external"]
                         ];
        
        if ($extra) {
            $flights[$i]["ETL"] = (int) $calcedData["ETL"];
            $flights[$i]["angle"] = (double) $calcedData["angle"];
        }

        $i++;
    }

    // check if there are any flights relative to that time
    if (count($flights) == 0) {
        $cnt_flights_ip = countFlightPerIP();
        if ($cnt_flights_ip == 0) {
            $msg = "You haven't uploaded any flights yet (or maybe your IP has changed). Please do that via POST request to api/FlightPlan";
        } else {
            $msg = "No flights found relative to this time.\n Make sure `relative_to` time is within the flight's timespan.";
        }
        error($msg);
    }

    $response = $flights;
}

/*
 * Delete a flight
 */
elseif ($_SERVER['REQUEST_METHOD'] === "DELETE" && isset($_GET['flight_id'])) {
    $flightName = escape($_GET['flight_id']);
    $sql = "DELETE FROM flights WHERE flightName = '$flightName' AND ip = '$remote_ip'";
    query($sql);

    if (mysqli_affected_rows($db) == 0) {
        error("Can't delete flight ".$flightName." because it doesn't exist");
    }
    
    $response = array("request" =>  $_SERVER['REQUEST_METHOD'], "status" => "success", "flight" => $flightName, "ip" => $remote_ip);
}
else {
    error("Please make a valid GET request (with `relative_to`) or DELETE request (api/Flights/{flight_id})"); 
 }

// echo output json
$response = json_encode($response);

//query("UPDATE logs SET `output` = '".escape($response)."' WHERE logID = '$last_log'");
echo $response;

?>