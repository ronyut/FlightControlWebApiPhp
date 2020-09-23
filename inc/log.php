<?php

// log activity
$query = query("SELECT * FROM uses WHERE ip = '$remote_ip' LIMIT 1");

if ($_SERVER['REQUEST_METHOD'] === "GET") {
    $details = json_encode($_GET);
} 
elseif ($_SERVER['REQUEST_METHOD'] === "POST") {
    $details = json_encode($input);
}
else {
    $details = escape($_SERVER["REQUEST_URI"]);
}
$request = $_SERVER['REQUEST_METHOD'];
$now = time() + 3600 * $gmt_offset;

//update existing record
if (mysqli_num_rows($query) == 1) {
    while ($row = mysqli_fetch_array($query)) {
        $count = $row["count"] + 1;

        if ($limit_enabled && !isset($_GET["unlimited"]) && $row["date_last_unix"] > $now - $min_query_interval) {
            error("Can't send requests more than once every $min_query_interval seconds");
        }

        query("UPDATE uses SET `count` = $count, date_last = '$date_time_gmt3', date_last_unix = '$now' WHERE ip = '$remote_ip'");
        break;
    }
}
// add new record
else {
    query("INSERT INTO uses (ip, `count`, `date_first`, `date_last`, `date_last_unix`)
                  VALUES ('$remote_ip', 1, '$date_time_gmt3', '$date_time_gmt3', '$now') ");
}

//query("INSERT INTO logs (ip, `date`, `request`, `input`) VALUES ('$remote_ip', '$date_time_gmt3','$request', '$details') ");
//$last_log = mysqli_insert_id($db);

/*
$query = query("SELECT * FROM uses WHERE date_last_unix = 0");
while ($row = mysqli_fetch_array($query)) {
    $id = $row["id"];

    $then = date(strtotime($row["date_last"]));
    query("UPDATE uses SET date_last_unix = '$then' WHERE id = '$id'");
}
*/

// get extra fields?
$extra = false;
if (isset($_GET["extra"])) {
    $extra = true;
}

?>