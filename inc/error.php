<?php

include("config.php");
include("functions.php");

error("Please remove the `ap2/` from URL to access the API", false,
        "Availabe servers: " . implode(", ", array_keys($hostMap)));

?>