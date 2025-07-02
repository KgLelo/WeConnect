<?php
function connectToDatabase() {
    $serverName = "MCEN2588";
    $connectionOptions = array(
        "Database" => "WConnDB",
        "Uid" => "",
        "Pwd" => ""
    );

    $conn = sqlsrv_connect($serverName, $connectionOptions);
    if ($conn === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    return $conn;
}
?>
