<?php
require "../connect.php";
require "../model/Authenticate.php";
require "../model/Users.php";

$authenticate = new Authenticate($con);
$users = new Users($con);


if(isset($_POST["createAccount"])) {
    
    $username = $_POST["username"];
    $firstname = $_POST["fname"];
    $lastname = $_POST["lname"];
    $mobile_no = str_replace(array(" ", "+") , "", $_POST["mobile"]);
    $emailaddress = $_POST["email"];
    
    print_r($users->createAccount($username, $firstname, $lastname, $emailaddress, $mobile_no));
    header("location: ". $_SERVER["HTTP_REFERER"]);
}

?>