<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set("Africa/Lagos");
session_start();

//DB Connection....
define('DB_NAME', 'peaktoppk_osb');
define('DB_USER', 'peaktoppk_osb');
define('DB_PASSWORD', 'peaktoppk_osb');
define('DB_HOST', 'localhost');

// To be set if going online or offline...
define('SCHEME', $_SERVER['REQUEST_SCHEME']);
define('SERVER', $_SERVER['SERVER_NAME']);
define('BASE_PATH', '/quorium/osb/'); //Site main folder
define('BASE_URL', SCHEME.'://'.SERVER.BASE_PATH);

define('UPLOAD_PATH', 'uploads/');

require_once('includes/db.php');

$dsn = "mysql:dbname=" . DB_NAME . ";host=" . DB_HOST . "";
$con = "";
try {
    $con = new PDO($dsn, DB_USER, DB_PASSWORD);
} catch (Throwable $e) {
    echo "Connection failed: " . $e->getMessage();
    die;
}

$con = new DB($con);

function arrayToObject($array) {
    return (object) $array;
}

function objectToArray($object) {
    return (array) $object;
}

function sendRequest($endpoint, $data) {
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    
    // execute!
    $response = curl_exec($ch);
    return $response;
}

?>