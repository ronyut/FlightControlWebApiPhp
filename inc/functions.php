<?php
	
	/**************************************************************
		error:
		Echo json error
	**************************************************************/

	function error($err, $critical = false, $additional_msg = "") {
		global $remote_ip;
		$request = $_SERVER['REQUEST_METHOD'];
        
        if ($request == "OPTIONS") {
            http_response_code(200);
        } else {
            http_response_code(400);
        }
        
		header('Content-Type: application/json');
        $response = array("request" => $request, "status" => "fail", "ip" => $remote_ip, "msg" => $err);
        if ($additional_msg != "") {
            $response["tech"] = $additional_msg;
        }
        $response = json_encode($response);
		echo $response;

		$err = escape(substr($err, 0, 40));
		//query("UPDATE logs SET `output` = '$err' WHERE ip = '$remote_ip' and request = '$request' ORDER BY logID DESC LIMIT 1");

		if ($critical) {
			//sendMail($additional_msg);
		}

        exit();
	}

	/**************************************************************
		sendMail:
		Send critical mail to me
	**************************************************************/
	function sendMail($msg) {
		global $remote_ip;
		$input = file_get_contents('php://input');

		// send mail
        $to = "ronyut@gmail.com";
        $from = "Admin <no-reply@".$_SERVER['HTTP_HOST'].">";
        $subject = "MySQL failure on ap2/api";
        $headers = "From: $from \r\n".
                    "MIME-Version: 1.0\r\n".
                    "Content-Type: text/html; charset=ISO-8859-1\r\n".
                    'X-Mailer: PHP/' . phpversion();

        $output = "<h1>Critical warning!</h1><hr><b>IP:</b>".$remote_ip."<br><br><br><b>Request:</b>".$_SERVER['REQUEST_METHOD']."<br><b>URI:</b>".$_SERVER['REQUEST_URI']."<br><b>POST data:</b>".json_encode($input)."<br><b>Details:</b>".$msg;
        mail($to, $subject, $output, $headers);
	}
	
	/**************************************************************
		isoToSqlDateTime:
		Convert ISO8601 to SQL datetime format
	**************************************************************/

	function isoToSqlDateTime($date) {
		$newDate = str_replace("Z", "", $date);
		$newDate = str_replace("T", " ", $newDate);
		return $newDate;
	}

	/**************************************************************
		sqlToIsoDateTime:
		Convert SQL to ISO8601 datetime format
	**************************************************************/

	function sqlToIsoDateTime($date) {
		$newDate = str_replace(" ", "T", $date);
		$newDate = $newDate."Z";
		return $newDate;
	}


	/**************************************************************
		isISO:
		Check if date is ISO8601
	**************************************************************/

	function isISO($date) {
		if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $date) && toUnix($date) != 0) {
			return true;
		}
		return false;
	}

	/**************************************************************
		toISO:
		Convert Unix to ISO8601
	**************************************************************/

	function toISO($date) {
		$iso = date("c", $date);
		return str_replace("+00:00", "Z", $iso);
	}

	/**************************************************************
		toUnix:
		Convert ISO8601 to Unix
	**************************************************************/
	function toUnix($date) {
		$unix = date("U",strtotime($date));
		return $unix;
	}

	/**************************************************************
		escape
		escape illegal chars
	**************************************************************/
	function escape($string) {
		global $db;
		return mysqli_real_escape_string($db, $string);
	}

	/**************************************************************
		query:
		Perform Mysqli query
	**************************************************************/
	function query($sql) {
		global $db;
		$query = mysqli_query($db, $sql) or error("A critical failure occurred - please contact me as soon as possible at ronyut@gmail.com", $critical = true, mysqli_error($db));
		return $query;
	}

	/**************************************************************
		Random strings:
	**************************************************************/
	function randomName() {
		return randomConsonant().randomVowel().randomConsonant().randomVowel().randomDigit().randomDigit();
	}

	function randomVowel() {
		$vowels = "AEIOU";
		return substr(str_shuffle($vowels), 0, 1);
	}

	function randomConsonant() {
		$cons = "BCDFGHJKLMNPQRSTVWXYZ";
		return substr(str_shuffle($cons), 0, 1);
	}
	
	function randomDigit() {
		$digits = "0123456789";
		return substr(str_shuffle($digits), 0, 1);
	}

	/**************************************************************
		calcFlight:
		Calculate current flight data - coords, angle and ETL
		(estimated time left)
	**************************************************************/
	function calcFlight($time_passed, $flightID) {
		$query = query("SELECT * FROM segments WHERE flightID = '$flightID' AND timespan_cdf >= $time_passed ORDER BY timespan_cdf ASC LIMIT 1");
		while ($row = mysqli_fetch_array($query)) {
			$prevCoord = getCoordByOrder($row["seg_order"] - 1, $flightID);
			$prevLong = $prevCoord["longitude"];
			$prevLat = $prevCoord["latitude"];

			if ($row["timespan"] == 0) {
				query("DELETE FROM flights WHERE flightID = '$flightID'");
				error("Total flight span is zero - this is a very strange error, please contact me.");
			}

			// calculate longitude
			$distLong = ($row["seg_long"] - $prevLong) * (($row["timespan"] - ($row["timespan_cdf"] - $time_passed)) / $row["timespan"]);
			$newLong = $prevLong + $distLong;
			
			// calculate latitude
			$distLat = ($row["seg_lat"] - $prevLat) * (($row["timespan"] - ($row["timespan_cdf"] - $time_passed)) / $row["timespan"]);
			$newLat = $prevLat + $distLat;
			
			// set the new coordination
			$newCoord = array("longitude" => roundDouble($newLong), "latitude" => roundDouble($newLat));

			// calculate angle
			$angle = calcAngle($prevCoord, $newCoord);

			// calculate ETL
			$flightData =  getFlightData($flightID);
			$etl = $flightData["landing_time_unix"] - $flightData["takeoff_time_unix"] - $time_passed;
			
			return array("coords" => $newCoord, "ETL" => $etl, "angle" => $angle);
		}

		error("No segments found for this flight - this is a very strange error, please contact me.");
	}
	
	/**************************************************************
		getCoordByOrder
	**************************************************************/
	function getCoordByOrder($order, $flightID) {
		if ($order > 0) {
			$query = query("SELECT * FROM segments WHERE flightID = '$flightID' AND seg_order = $order LIMIT 1");
			while ($row = mysqli_fetch_array($query)) {
				return array("longitude" => $row["seg_long"], "latitude" => $row["seg_lat"]);
			}
		} else {
			$query = query("SELECT * FROM flights WHERE flightID = '$flightID' LIMIT 1");
			while ($row = mysqli_fetch_array($query)) {
				return array("longitude" => $row["longitude"], "latitude" => $row["latitude"]);
			}
		}

		error("No segments found for this flight - this is a very strange error, please contact me.");
	}

	/**************************************************************
		roundDouble
	**************************************************************/
	function roundDouble($double) {
		$precision = 6;
		return number_format((double) $double, $precision, '.', '');
	}

	/**************************************************************
		getFlightData
	**************************************************************/
	function getFlightData($flightID) {
		$query = query("SELECT * FROM flights WHERE flightID = '$flightID' LIMIT 1");
		while ($row = mysqli_fetch_array($query)) {
			return array("landing_time_unix" => $row["landing_time_unix"], "takeoff_time_unix" => $row["takeoff_time_unix"]);
		}

		error("No flight with internal ID of ".$flightID);
	}

	/**************************************************************
		calcAngle
	**************************************************************/
	function calcAngle($from, $to) {
		$x_dist = $to["longitude"] - $from["longitude"];
		$y_dist = $to["latitude"] - $from["latitude"];

		if ($x_dist == 0) {
			if ($y_dist > 0) {
				$angle = 90;
			} else {
				$angle = 180;
			}
		} else {
			$angle = atan($y_dist / $x_dist);

			if ($x_dist > 0 && $y_dist > 0) {
				$add = 0;
			} elseif ($x_dist > 0 && $y_dist < 0) {
				$add = 360;
			} elseif ($x_dist < 0 && $y_dist > 0) {
				$add = 180;
			} else {
				$add = 180;
			}

			$angle = rad2deg($angle) + $add;
		}

		return $angle;
	}

	/**************************************************************
		isLatitude
	**************************************************************/
	function isLatitude($lat) {
		$edge = 90;
		if (is_numeric($lat) && (double) $lat >= -$edge && (double) $lat <= $edge) {
			return true;
		}
		return false;
	}

	/**************************************************************
		isLongitude
	**************************************************************/
	function isLongitude($long) {
		$edge = 180;
		if (is_numeric($long) && (double) $long >= -$edge && (double) $long <= $edge) {
			return true;
		}
		return false;
	}

	/**************************************************************
		countFlightPerIP
	**************************************************************/
	function countFlightPerIP ($ip = "") {
		global $remote_ip;
		// default ip
		if ($ip == "") {
			$ip = $remote_ip;
		}

		$query = query("SELECT count(flightID) as cnt FROM flights WHERE ip = '$ip'");
		while ($row = mysqli_fetch_array($query)) {
			return $row["cnt"];
		}

	}

?>