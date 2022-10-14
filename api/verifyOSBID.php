<?php
require "../connect.php";

#############################
# This API works in two form
# 1. verifying  with username only
# 2. verifying with username & password
# 
#
# Do not modify if you do not understand
# Contact : 0903-302-4846
# oluwatayoadeyemi@yahoo.com
#############################


$username = !empty($_REQUEST["userId"]) ? $_REQUEST["userId"]:NULL;
$password = !empty($_REQUEST["userPass"]) ? $_REQUEST["userPass"]:NULL;

if($username !== NULL) {
    $searchUser = $con->getSingleRecord("tblusers", "*", " AND osmusernameosm = '$username'");
    
    if($searchUser !== false) {
        if($password !== NULL) {
            if($password == "0000") {
                $response = ["status"=>false, "message" => "You cannot use default transaction pin to perform a transaction"];
            }
            else if($searchUser['ussd_id'] == $password) {
                $response = $searchUser;
                $response['wallet_balance'] = UserBalance($username);
            }
            else {
                $response = ["status"=>false, "message" => "Incorrect password supplied"];
            }
        }
        else {
            $response = $searchUser;
            $response['wallet_balance'] = UserBalance($username);
        }
    }
    else {
        $response = ["status"=>false, "message" => "OSB ID ($username) not found"];
    }
}
else {
    $response = ["status"=>false, "message" => "OSB ID not provided"];
}
echo json_encode($response, JSON_PRETTY_PRINT);

function UserBalance($systemId) {
    global $con;
    $amountLeft = 0;
    
    // $totalSavings = "select SUM(amountpaid) from tbldaily_savings where systemid = '$systemId'";
    
    $getSavings = $con->getSingleRecord("tbldaily_savings", " COALESCE(sum(amountpaid), 0) as amount_deposited", " AND systemid = '$systemId'");
    
    $getWithdrawal = $con->getSingleRecord("tbldaily_withdrawals", " COALESCE(sum(amount), 0) as amount_withdrawn", " AND systemid = '$systemId'");
    
    $amountLeft = (float)($getSavings['amount_deposited'] - $getWithdrawal['amount_withdrawn']);
    
    return $amountLeft;
    
}