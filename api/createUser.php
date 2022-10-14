<?php
require "../connect.php";

require_once "../model/Authenticate.php";
require_once "../model/VirtualAccount.php";

$authenticate = new Authenticate($con);
$virtaccount = new VirtualAccount($con);

#############################
# This API is for creating OSB Account
# As well as generating FCMB Account
# On Account creation
#
# Do not modify if you do not understand
# Contact : 0903-302-4846
# oluwatayoadeyemi@yahoo.com
#############################

$firstname = !empty($_REQUEST["firstname"]) ? $_REQUEST["firstname"]:NULL;
$lastname = !empty($_REQUEST["lastname"]) ? $_REQUEST["lastname"]:NULL;
$phoneNo = !empty($_REQUEST["phoneNo"]) ? $_REQUEST["phoneNo"]:NULL;

if($firstname != NULL  AND $lastname != NULL  AND $phoneNo != NULL) {
    $fullname = $lastname. " ".$firstname;
    $createAccount = $con->insertRecord('tblusers', [
        "fname" => $firstname,
        "lname" => $lastname,
        "name" => $fullname,
        "mobile" => $phoneNo,
        "ussd_id" => "0000",
        "locationcode" => "7173040752"
    ]);
    
    $userId = $con->lastInsertId();
    
    $generateVirtual = $virtaccount->generatesingle_virtual($fullname);
    
    //Let's decode the response gotten from V.A...
    $dcodeVa = json_decode($generateVirtual);
    
    if(isset($dcodeVa->status) AND $dcodeVa->status == 'success' AND isset($dcodeVa->status) AND $dcodeVa->status == 'success') {
        $virtualAccount_Gen = $dcodeVa->account_number;
        
        // Let's create login credential as well and use account number as password......
        $createAccount = $con->insertRecord('tbllogin', [
            "osmusernameosm" => $virtualAccount_Gen,
            "osmpasswordosm" => $virtualAccount_Gen,
            "systemid" => $virtualAccount_Gen,
            "wemasystemid" => $virtualAccount_Gen,
            "systemid2" => $virtualAccount_Gen,
            "agentapproved" => "Yes",
            "registrarapproved" => "Yes"
        ]);
        
        // Let's update...
        $con->updateTable('tblusers', 
            [
                "osmusernameosm" => $virtualAccount_Gen,
                "wemasystemid" => $virtualAccount_Gen,
                "systemid" => $virtualAccount_Gen
            ],
            ['ID' => $userId]
        );
        $response = ["message" => "Account created successfully", "virtual_account" => $virtualAccount_Gen];
    }
    else {
        $con->deleteRecord('tblusers', ['ID' => $userId]);
        $response = ["status"=>false, "message" => "Error creating account, Kindly try again later"];
    }
}
else {
    $response = ["status"=>false, "message" => "Missing parameter field"];
}
echo json_encode($response, JSON_PRETTY_PRINT);

function reformNo($phoneNo) {
    if(substr(str_replace("+", "" , $phoneNo), 0, 3) == '234') {
        $result = '0'.substr(str_replace("+", "" , $phoneNo), 3, 13);
    } else {
        $result = $phoneNo;
    }
    return $result;
}
