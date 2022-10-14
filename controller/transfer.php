<?php
require_once "../connect.php";
require_once "../model/Authenticate.php";
require_once "../model/VirtualAccount.php";
require_once "../model/Banks.php";

new Authenticate($con);
$banks = new Banks($con);

$allSettings = getAllSettings();
$cot = $allSettings->COT;
    
// Hardcoded....
$systemTfusername = "cc7938dad84a4b10a29d115f88ec567a";
$systemTfpassword = "&o638O&Tkv@P!_eWU5xCw#ts";

if(isset($_POST["initiateFCMBTransfer"])) {
    
    $accountNo = $_POST["accountNo"];
    $amount = filter_var($_POST["amount"], FILTER_SANITIZE_NUMBER_FLOAT);
    $locationcode = isset($_POST["locationcode"]) ? $_POST["locationcode"] : 000;
    $locationcode = filter_var($locationcode, FILTER_SANITIZE_NUMBER_INT);
    $systemId = filter_var($_POST["systemId"], FILTER_SANITIZE_NUMBER_FLOAT);
    $secretusername = filter_var($_POST["secretusername"], FILTER_SANITIZE_STRING);
    $secretpassword = filter_var($_POST["secretpassword"], FILTER_SANITIZE_STRING);
    
    //How much does client has in his/her wallet...
    $clientBalance = UserBalance($systemId);
    $userInfo = getClientDetail($systemId);
    
    $_SESSION['titleMessage'] = "Error";
    
    if(!isset($_SERVER["HTTP_REFERER"]) OR $_SERVER["HTTP_REFERER"] != 'https://peaktopup.com/quorium/osb/fcmbInternal') {
        $_SESSION['errorMessage'] = "Unauthorized Access";
    }
    else if($userInfo == false) {
        $_SESSION['errorMessage'] = "User system id ($systemId) does not exists";
    }
    else {
        
        $client_name = $userInfo->fname. " ".$userInfo->lname;
        
        // Add COT & Amount together...
        $amountCOT = (float)($cot + $amount);
        
        if(strlen($accountNo) < 10 OR strlen($accountNo) > 10) {
            $_SESSION['errorMessage'] = "Invalid NUBAN Account number provided";
        }
        else if($systemTfusername != $secretusername AND $systemTfpassword != $secretpassword) {
            $_SESSION['errorMessage'] = "Unauthorized Access, System details are invalid";
        }
        else if($amountCOT > $clientBalance) {
            $_SESSION['errorMessage'] = "Insufficient wallet balance (&#8358;".number_format($clientBalance, 2).")";
        }
        else {
            
            $verifyAccount = $banks->verifyFCMBAccount($accountNo);
            $dcodeResponse = json_decode($verifyAccount);
            $_SESSION['verify_account'] = $verifyAccount;
            
            if($dcodeResponse->code != 00) {
                $_SESSION['errorMessage'] = "System Error, Account validation failed";
            }
            else {
                $initiateTransfer = $banks->FcmbToFcmbTransfer($accountNo, $amount);
                
                $_SESSION['initiate_tf'] = $initiateTransfer;
                
                $decode_initiate = json_decode($initiateTransfer, true);
                $requestId = $decode_initiate['request_id'];
                if($decode_initiate['status'] == 200) {
                    
                    $principalDebit_description = "Transfer of NGN".$amount." to ".$dcodeResponse->data->accT_NAME.", ".$accountNo;
                    $COTDebit_description = "COT of NGN".$cot . " for transfer of NGN".$amount . " to ".$dcodeResponse->data->accT_NAME.", ".$accountNo;
                    
                    $principalData = [
                        "invoiceno" => $requestId,
                        "systemid" => $systemId,
                        "systemid2" => $systemId,
                        "wemasystemid" => NULL,
                        "clientname" => $client_name,
                        "amount" => $amount,
                        "paymentdatesql" => date("Y-m-d"),
                        "withdrawaldate" => date("d/m/Y"),
                        "withdrawaltime" => date("H:i:s a"),
                        "author" => "OsB Money Transfer",
                        "locationcode" => $locationcode,
                        "ddate" => date("d/m/Y"),
                        "ddates" => date("m/d/Y"),
                        "dweek" => NULL,
                        "done" => "Yes",
                        "request" => NULL,
                        "companyname" => "Osemo Borealis Ltd.",
                        "branchid" => "000001",
                        "taken" => "Yes",
                        "corrected" => "Yes",
                        "definition" => $principalDebit_description
                    ];
                    
                    $cotData = [
                        "invoiceno" => $requestId,
                        "systemid" => $systemId,
                        "systemid2" => $systemId,
                        "wemasystemid" => NULL,
                        "clientname" => $client_name,
                        "amount" => $cot,
                        "paymentdatesql" => date("Y-m-d"),
                        "withdrawaldate" => date("d/m/Y"),
                        "withdrawaltime" => date("H:i:s a"),
                        "author" => "OsB COT Charges",
                        "locationcode" => $locationcode,
                        "ddate" => date("d/m/Y"),
                        "ddates" => date("m/d/Y"),
                        "dweek" => NULL,
                        "done" => "Yes",
                        "request" => NULL,
                        "companyname" => "Osemo Borealis Ltd.",
                        "branchid" => "000001",
                        "taken" => "Yes",
                        "corrected" => "Yes",
                        "definition" => $COTDebit_description
                    ];
                    
                    
                    
                    $creditCotData = [
                        "invoiceno" => $requestId,
                        "systemid" => "0000000003",
                        "systemid2" => "0000000003",
                        "wemasystemid" => NULL,
                        "clientname" => "OsB Transaction COT Ledger",
                        "paymentdate" => date("d/m/Y"),
                        "paymenttime" => date("H:i:s a"),
                        "paymentdatesql" => date("Y-m-d"),
                        "amountpaid" => $cot,
                        "goal" => "OsB Transaction COT Ledger",
                        "definition" => $COTDebit_description,
                        "earnings" => NULL,
                        "earningspaid" => NULL,
                        "author" => "OsB COT Charges",
                        "locationcode" => $locationcode,
                        "ddate" => date("d/m/Y"),
                        "ddates" => date("m/d/Y"),
                        "dweek" => NULL,
                        "done" => "0000000003",
                        "request" => NULL,
                        "agent" => NULL,
                        "savings" => NULL,
                        "accounts" => NULL,
                        "md" => NULL,
                        "updateamt" => NULL,
                        "companyname" => "Osemo Borealis Ltd.",
                        "branchid" => "000001",
                        "corrected" => "Yes",
                        "duplicate_entries" => NULL
                    ];
                    
                    $con->insertRecord("tbldaily_withdrawals", $principalData);
                    $con->insertRecord("tbldaily_withdrawals", $cotData);
                    
                    $con->insertRecord("tbldaily_savings", $creditCotData);
                    
                    $_SESSION['successMessage'] = $decode_initiate['message'];
                    $_SESSION['titleMessage'] = "Success";
                }
                else {
                    $_SESSION['errorMessage'] = $decode_initiate['message'];
                }
            }
        }
    }
    header("location: ".$_SERVER['HTTP_REFERER']);
    exit;
}

else if(isset($_POST["createTransfer"])) {
    $bankCode = filter_var($_POST["bankName"], FILTER_SANITIZE_STRING);
    $systemId = filter_var($_POST["systemId"], FILTER_SANITIZE_STRING);
    $accountNo = filter_var($_POST["accountNo"], FILTER_SANITIZE_STRING);
    $amount = filter_var($_POST["amount"], FILTER_SANITIZE_STRING);
    $accountPassword = filter_var($_POST["accountPassword"], FILTER_SANITIZE_STRING);
    
    $secretusername = filter_var($_POST["secretusername"], FILTER_SANITIZE_STRING);
    $secretpassword = filter_var($_POST["secretpassword"], FILTER_SANITIZE_STRING);

    $_SESSION['titleMessage'] = "Error";
    $_SESSION['systemid'] = $systemId;
    
    // How much does client has in his/her wallet...
    $clientBalance = UserBalance($systemId);
    
    // get user details
    $userInfo = getClientDetail($systemId);
    
    // Get client login table...
    $clientLogin = getClientLogin($systemId);
    $savedPass = isset($clientLogin->osmpasswordosm) ? $clientLogin->osmpasswordosm : NULL;
    unset($userInfo->osmpasswordosm);
    
    if(!isset($_SERVER["HTTP_REFERER"]) OR $_SERVER["HTTP_REFERER"] != 'https://peaktopup.com/quorium/osb/fcmbExternal') {
        $_SESSION['errorMessage'] = "Unauthorized Access";
    }
    else if($userInfo == false) {
        $_SESSION['errorMessage'] = "User system id ($systemId) does not exists";
    }
    else if($savedPass === NULL) {
        $_SESSION['errorMessage'] = "Password field cannot be empty.";
    }
    else if(strtolower($accountPassword) != strtolower($savedPass)) {
        $_SESSION['errorMessage'] = "Incorrect password supplied. Provide a valid password and try again";
    }
    else {
        $client_name = $userInfo->fname. " ".$userInfo->lname;
        
        // Add COT & Amount together...
        $amountCOT = (float)($cot + $amount);
        
        if(strlen($accountNo) < 10 OR strlen($accountNo) > 10) {
            $_SESSION['errorMessage'] = "Invalid NUBAN Account number provided";
        }
        else if($systemTfusername != $secretusername AND $systemTfpassword != $secretpassword) {
            $_SESSION['errorMessage'] = "Unauthorized Access, System details are invalid";
        }
        else if($amountCOT > $clientBalance) {
            $_SESSION['errorMessage'] = "Insufficient wallet balance (&#8358;".number_format($clientBalance, 2).")";
        }
        else {
            $principalDebit_description = "Transfer of NGN".$amount." to ".$_POST["accountName"].", ".$accountNo;
            $COTDebit_description = "COT of NGN".$cot . " for transfer of NGN".$amount . " to ".$_POST["accountName"].", ".$accountNo;
            $requestId = "OsB_RQID".$con->randID(7);
            
            $principalData = [
                "invoiceno" => $requestId,
                "systemid" => $systemId,
                "systemid2" => $systemId,
                "wemasystemid" => NULL,
                "clientname" => $client_name,
                "amount" => $amount,
                "paymentdatesql" => date("Y-m-d"),
                "withdrawaldate" => date("d/m/Y"),
                "withdrawaltime" => date("H:i:s a"),
                "author" => "OsB Money Transfer",
                "locationcode" => "000",
                "ddate" => date("d/m/Y"),
                "ddates" => date("m/d/Y"),
                "dweek" => NULL,
                "done" => "No",
                "request" => NULL,
                "companyname" => "Osemo Borealis Ltd.",
                "branchid" => "000001",
                "taken" => "No",
                "corrected" => "No",
                "definition" => $principalDebit_description
            ];
            
            $cotData = [
                "invoiceno" => $requestId,
                "systemid" => $systemId,
                "systemid2" => $systemId,
                "wemasystemid" => NULL,
                "clientname" => $client_name,
                "amount" => $cot,
                "paymentdatesql" => date("Y-m-d"),
                "withdrawaldate" => date("d/m/Y"),
                "withdrawaltime" => date("H:i:s a"),
                "author" => "OsB COT Charges",
                "locationcode" => "000",
                "ddate" => date("d/m/Y"),
                "ddates" => date("m/d/Y"),
                "dweek" => NULL,
                "done" => "Yes",
                "request" => NULL,
                "companyname" => "Osemo Borealis Ltd.",
                "branchid" => "000001",
                "taken" => "No",
                "corrected" => "No",
                "definition" => $COTDebit_description
            ];
            
            $creditCotData = [
                "invoiceno" => $requestId,
                "systemid" => "0000000003",
                "systemid2" => "0000000003",
                "wemasystemid" => NULL,
                "clientname" => "OsB Transaction COT Ledger",
                "paymentdate" => date("d/m/Y"),
                "paymenttime" => date("H:i:s a"),
                "paymentdatesql" => date("Y-m-d"),
                "amountpaid" => $cot,
                "goal" => "OsB Transaction COT Ledger",
                "definition" => $COTDebit_description,
                "earnings" => NULL,
                "earningspaid" => NULL,
                "author" => "OsB COT Charges",
                "locationcode" => "000",
                "ddate" => date("d/m/Y"),
                "ddates" => date("m/d/Y"),
                "dweek" => NULL,
                "done" => "No",
                "agent" => NULL,
                "savings" => NULL,
                "accounts" => NULL,
                "md" => NULL,
                "updateamt" => NULL,
                "companyname" => "Osemo Borealis Ltd.",
                "branchid" => "000001",
                "corrected" => "No",
                "duplicate_entries" => NULL
            ];
            
            $pendingRequest = [
                "systemid" => $systemId,
                "client_name" => $client_name,
                "requestid" => $requestId,
                "bank_code" => $bankCode,
                "bank_name" => $banks->getBankByBankCode($bankCode)['bank_name'],
                "accountNo" => $accountNo,
                "account_name" => $_POST["accountName"],
                "amount" => $amount,
                "date_updated" => date("Y-m-d H:i:s"),
                "date_created" => date("Y-m-d H:i:s")
            ];
            
            $con->insertRecord("tbldaily_withdrawals", $principalData);
            $con->insertRecord("tbldaily_withdrawals", $cotData);
            
            $con->insertRecord("tbldaily_savings", $creditCotData);
            $con->insertRecord("tblwithdrawal_request", $pendingRequest);
            
            $_SESSION['successMessage'] = "Withdrawal request has been initiated. You will be funded shortly";
            $_SESSION['titleMessage'] = "Success";
        }
    }
    header("location: ".$_SERVER['HTTP_REFERER']);
    exit;
}


// print_r($_POST);

// function to read user Balance...
function UserBalance($systemId) {
    global $con;
    $amountLeft = 0;
    
    // $totalSavings = "select SUM(amountpaid) from tbldaily_savings where systemid = '$systemId'";
    
    $getSavings = $con->getSingleRecord("tbldaily_savings", " COALESCE(sum(amountpaid), 0) as amount_deposited", " AND systemid = '$systemId'");
    
    $getWithdrawal = $con->getSingleRecord("tbldaily_withdrawals", " COALESCE(sum(amount), 0) as amount_withdrawn", " AND systemid = '$systemId'");
    
    $amountLeft = (float)($getSavings['amount_deposited'] - $getWithdrawal['amount_withdrawn']);
    
    return $amountLeft;
    
}

function getAllSettings() {
    global $con;
    
    $getSettings = $con->getAllRecords("tbl__fcmb_settings", "*", ""); 
    foreach($getSettings as $index => $record) {
        $feedback[$record['name']] = $record['value'];
    }
    return arrayToObject($feedback);
}

function getClientDetail($systemId) {
    global $con;
    
    $getSettings = $con->getSingleRecord("tblusers", "*", " AND systemid = '$systemId'"); 
    if($getSettings != NULL) {
        $response = arrayToObject($getSettings);
    } else {
        $response = false;
    }
    return $response;
}

function getClientLogin($systemId) {
    global $con;
    
    $getLogin = $con->getSingleRecord("tbllogin", "*", " AND osmusernameosm = '$systemId'"); 
    if($getLogin != NULL) {
        $response = arrayToObject($getLogin);
    } else {
        $response = false;
    }
    return $response;
}

?>