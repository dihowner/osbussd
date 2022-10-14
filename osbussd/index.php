<?php
require_once "../connect.php";
require_once "../model/Authenticate.php";
require_once "../model/Banks.php";

error_reporting(E_ALL);

new Authenticate($con);
$banks = new Banks($con);

//Get the content from AT Gateway...
$sessionId = $_POST["sessionId"];
$serviceCode = $_POST["serviceCode"];
$phoneNumber = str_replace("+", "" , $_POST["phoneNumber"]);
$text = urldecode($_POST["text"]);
$text = str_replace(" ", "", $text);

$allSettings = getAllSettings();
$cot = $allSettings->COT_USSD;
            
$requestId = "OsB_RQID".$con->randID(7);

//Explode Request...
$explodeText = explode("*", $text);

// $phoneNumber = '2348179653448';

// this is a mobile number we are not certain if record exists with us or not...
$srchMobile = $con->getSingleRecord("tblusers", "*", " AND (mobile  = '$phoneNumber' OR mobile  = '".reformNo($phoneNumber)."')", "");

$response = "";

if($srchMobile === FALSE) {
    if($text == "") {
        $response .= "CON Hi ".$phoneNumber.", Would you like to create your Osb Wallet ? \n \n";
        $response .= "1. Create Account \n";
        $response .= "2. No, Thanks \n";
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "1" AND isset($explodeText['1']) AND isset($explodeText['2'])) {
        $firstname = $explodeText['1'];
        $lastname = $explodeText['2'];
        
        $processData = [
            "firstname" => ucfirst($firstname),
            "lastname" => ucfirst($lastname),
            "phoneNo" => reformNo($phoneNumber)
        ];
        
        // Let's invoke the API for account creation...
        $processResponse = sendRequest(BASE_URL."api/createUser.php", $processData);
        $decode_verify = json_decode($processResponse);
        
        if(isset($decode_verify->status)) {
            $response .= "END ".$decode_verify->message;
        } 
        else {
            $response .= "END Your Osb account creation was successful. \n\n Username : ".$decode_verify->virtual_account." \n Password : ".$decode_verify->virtual_account. " \n \n Kindly login to osb.ng to complete your registration";
        }
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "1" AND isset($explodeText['1'])) {
        $response .= "CON Hi ".$phoneNumber.", \n Ente your Last Name \n";
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "1") {
        $response .= "CON Hi ".$phoneNumber.", \n Ente your First Name \n";
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "2") {
        $response .= "END Thanks for your time, we would like to have you on Osb to enjoy amazing offers";
    }
    else {
        $response .= "END Invalid request, Kindly try again later";
    }
}
else {
    
    //Check user balance and join it's array to existing user array...
    $srchMobile['wallet_balance'] = UserBalance($srchMobile['systemid']);
    
    //Let's conver the array to object...
    $userObj = arrayToObject($srchMobile);
    $clientname = $userObj->lname . " ".$userObj->fname;
    $systemId = $userObj->systemid;
    $user_ussd_id = $userObj->ussd_id;
    $currBalance = $userObj->wallet_balance;
    
    if($text == "") {
        $response .= "CON Hi ".$clientname.", What do you want to do ? \n \n";
        $response .= "1. Osb Wallet Transfer \n";
        $response .= "2. Osb to Bank Transfer \n";
        $response .= "3. Osb Wallet Balance Check \n";
        $response .= "4. Reset USSD PIN \n";
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "1" AND isset($explodeText['1']) AND isset($explodeText['2']) AND isset($explodeText['3'])) {
        $amount = $explodeText['1'];
        $receiver_WalletID = $explodeText['2'];
        $ussdPassword = $explodeText['3'];
        
        if($ussdPassword == "0000") {
            $respMsg = "Default USSD PIN (0000) cannot be used";
        }
        else if($user_ussd_id != $ussdPassword) {
            $respMsg = "Password do not match";
        }
        else if($currBalance < $amount) { //Let's verify sender wallet
            $respMsg = "Insufficient OSB Wallet Balance. Transaction could not be completed";
        }
        else {
            
            /*
                Process the Withdrawal Here....
            */
        
            $processData = [
                "senderId" => $systemId,
                "receiverId" => $receiver_WalletID,
                "amount" => $amount,
                "ussd_pin" => $user_ussd_id
            ];
            
            $processResponse = sendRequest(BASE_URL."api/WalletTransfer.php", $processData);
        
            $decode_response = json_decode($processResponse);
            
            if(isset($decode_response->status)) {
                $respMsg = $decode_response->message;
            }
            else {
                $respMsg = "Error processing request. Kindly try again";
            }
        }
        
        $response .= "END ".$respMsg;
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "1" AND isset($explodeText['1']) AND isset($explodeText['2'])) {
        // let's verify the receiving osb wallet id 
        
        $amount = $explodeText['1'];
        $receiver_WalletID = $explodeText['2'];
        
        $processData = [
            "userId" => $receiver_WalletID,    
        ];
        $processResponse = sendRequest(BASE_URL."api/verifyOSBID.php", $processData);
        
        $decode_response = json_decode($processResponse);
        
        if(isset($decode_response->status)) {
            $response .= "END ".$decode_response->message;
        }
        else {
            $receiver_customer_name = $decode_response->fname. " ".$decode_response->lname;
            $response .= "CON Hi ".$clientname."\n You are about to send N".number_format($amount, 2). " to ". $receiver_customer_name. " (".$receiver_WalletID.") \n \n Enter your OSB USSD Password";
        }
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "1" AND isset($explodeText['1'])) {
        $response .= "CON Enter receiver OSB Wallet ID";
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "1") {
        $response .= "CON Hi ".$clientname ."\n \n Enter amount you wish to share";
    }
    
    else if(isset($explodeText['0']) AND $explodeText['0'] == "2" AND isset($explodeText['1']) AND isset($explodeText['2']) AND isset($explodeText['3']) AND isset($explodeText['4']) AND isset($explodeText['5']) AND isset($explodeText['6']) AND $explodeText['3'] == "6") {
        
        /* Now let's check if the input is right */
        $amount = $explodeText['1'];
        $receiving_accountNo = $explodeText['2'];
        $ussdPass = $explodeText['6'];
        $bank_input = $explodeText['5'];
        
        if($ussdPass == "0000") {
            $respMsg = "Default USSD PIN (0000) cannot be used";
        }
        else if($user_ussd_id != $ussdPass) {
            $respMsg = "Password do not match";
        } 
        else if($currBalance < $amount) {
            $respMsg = "Insufficient OSB Wallet Balance. Transaction could not be completed";
        }
        else {
            
            // WHich bank did user selected, do not forget that we used 'array_id' to hold user input...
            $getBank = $con->getSingleRecord("tbltemp_banklist", "*", " AND array_id ='$bank_input' AND action = 'search' AND session_id = '$sessionId'", " ORDER BY id desc");
            
            //Re-verify so we can form the description in full...
            $verifyAccount = $banks->verifyAccount($getBank['bank_code'], $receiving_accountNo);
            $decode_response = json_decode($verifyAccount, true)['data'];
            $accountName = $decode_response['accountName'];  
            
            $principalDebit_description = "Transfer of NGN".$amount . " to ".$accountName.", ".$receiving_accountNo;
            $COTDebit_description = "COT of NGN".$cot . " for transfer of NGN".$amount . " to ".$accountName.", ".$receiving_accountNo;
            
            $principalData = [
                "invoiceno" => $requestId,
                "systemid" => $systemId,
                "systemid2" => $systemId,
                "wemasystemid" => NULL,
                "clientname" => $clientname,
                "amount" => $amount,
                "paymentdatesql" => date("Y-m-d"),
                "withdrawaldate" => date("d/m/Y"),
                "withdrawaltime" => date("H:i:s a"),
                "author" => "OSB Money Transfer",
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
                "clientname" => $clientname,
                "amount" => $cot,
                "paymentdatesql" => date("Y-m-d"),
                "withdrawaldate" => date("d/m/Y"),
                "withdrawaltime" => date("H:i:s a"),
                "author" => "OSB COT Charges",
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
                "clientname" => "OSB Transaction COT Ledger",
                "paymentdate" => date("d/m/Y"),
                "paymenttime" => date("H:i:s a"),
                "paymentdatesql" => date("Y-m-d"),
                "amountpaid" => $cot,
                "goal" => "OSB Transaction COT Ledger",
                "definition" => $COTDebit_description,
                "earnings" => NULL,
                "earningspaid" => NULL,
                "author" => "OSB COT Charges",
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
                "client_name" => $clientname,
                "requestid" => $requestId,
                "bank_code" => $getBank["bank_code"],
                "bank_name" => $getBank["bank_name"],
                "account_name" => $accountName,
                "accountNo" => $receiving_accountNo,
                "amount" => $amount,
                "date_updated" => date("Y-m-d H:i:s"),
                "date_created" => date("Y-m-d H:i:s")
            ];
            
            $con->insertRecord("tbldaily_withdrawals", $principalData);
            $con->insertRecord("tbldaily_withdrawals", $cotData);
            
            $con->insertRecord("tbldaily_savings", $creditCotData);
            $con->insertRecord("tblwithdrawal_request", $pendingRequest);
            
            // Since we are done then let's delete the tmpbank_temp list for this session id...
            $con->deleteRecord("tbltemp_banklist", ["session_id" => $sessionId]);
            
            // Delete uncompleted transactions...
            $dateLike = date("Y-m-d");
            $con->runQuery("delete from tbltemp_banklist where date_created NOT LIKE '%$dateLike%");
            
            $respMsg = "Your request has been received. N".number_format($amount, 2)." will be deposited into ".$receiving_accountNo. " ".$getBank["bank_name"]. " \n Thank You";
        }
        $response .= "END ".$respMsg;
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "2" AND isset($explodeText['1']) AND isset($explodeText['2']) AND isset($explodeText['3']) AND isset($explodeText['4']) AND isset($explodeText['5']) AND $explodeText['3'] == "6") {
        $bank_input = $explodeText['5'];
        
        // WHich bank did user selected, do not forget that we used 'array_id' to hold user input...
        $getBank = $con->getSingleRecord("tbltemp_banklist", "*", " AND array_id ='$bank_input' AND action = 'search' AND session_id = '$sessionId'", " ORDER BY id desc");
        
        // We need to verify the receiving account number...
        $bankCode = $getBank["bank_code"];
        $bank_name = $getBank["bank_name"];
        $receiving_accountNo = $explodeText['2'];
        $amount = $explodeText['1'];
        
        $verifyAccount = $banks->verifyAccount($bankCode, $receiving_accountNo);
        $decode_response = json_decode($verifyAccount, true)['data'];
        
        if($decode_response['responseCode'] == 00) {
            $accountName = $decode_response['accountName'];   
            $response .= "CON Transfer of N".number_format($amount, 2). " to ". $accountName. " ".$receiving_accountNo . " at N".number_format($cot, 2)." charge \n Please enter your OSB PIN";
        } else {
            $response .= "END We could not verify the account number (".$receiving_accountNo.") belonging to ".$bank_name;
        }
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "2" AND isset($explodeText['1']) AND isset($explodeText['2']) AND isset($explodeText['3']) AND isset($explodeText['4']) AND $explodeText['3'] == "6") {
       
        // Let's perform a search on the database...
        $bankfirst3Letter = $explodeText['4'];
        
        $searchBanks = $con->getAllRecords("tblbanks_list", "*", " AND bank_name like '%$bankfirst3Letter%' AND use_ussd = 'yes'", " order by id desc");
        if($searchBanks == NULL) {
            $response .= "END No search result found, please try again later";
        }
        else {
            $i = 1;
            $response .= "CON Select Recipient Bank \n";
            foreach($searchBanks as $searchBank) {
                $response .= $i." ".removeBracket($searchBank["bank_name"]) . "\n";
                
                //Since session & cookie didn't work then store it to DB
                $con->insertRecord("tbltemp_banklist", [
                    "session_id" => $sessionId,
                    "array_id" => $i,
                    "bank_name" => $searchBank["bank_name"],
                    "bank_code" => $searchBank["bank_code"],
                    "action" => "search"
                ]);
                $i++;
            }
        }
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "2" AND isset($explodeText['1']) AND isset($explodeText['2']) AND isset($explodeText['3']) AND $explodeText['3'] == "6") {
        $response .= "CON Enter first 3 letter of bank name";
    }
    
    
    else if(isset($explodeText['0']) AND $explodeText['0'] == "2" AND isset($explodeText['1']) AND isset($explodeText['2']) AND isset($explodeText['3']) AND isset($explodeText['4']) AND $explodeText['3'] != "6") {
        
        /* Now let's check if the input is right */
        
        $amount = $explodeText['1'];
        $receiving_accountNo = $explodeText['2'];
        $bank_input = $explodeText['3'];
        $ussdPass = $explodeText['4'];
        
        if($ussdPass == "0000") {
            $respMsg = "Default USSD PIN (0000) cannot be used";
        }
        else if($user_ussd_id != $ussdPass) {
            $respMsg = "Password do not match";
        } 
        else if($currBalance < $amount) {
            $respMsg = "Insufficient OSB Wallet Balance. Transaction could not be completed";
        }
        else {
             // WHich bank did user selected, do not forget that we used 'array_id' to hold user input...
            $getBank = $con->getSingleRecord("tbltemp_banklist", "*", " AND array_id ='$bank_input' AND action = 'populate' AND session_id = '$sessionId'", " ORDER BY id desc");
            
            //Re-verify so we can form the description in full...
            $verifyAccount = $banks->verifyAccount($getBank['bank_code'], $receiving_accountNo);
            $decode_response = json_decode($verifyAccount, true)['data'];
            $accountName = $decode_response['accountName'];
            
            $principalDebit_description = "Transfer of NGN".$amount . " to ".$accountName.", ".$receiving_accountNo;
            $COTDebit_description = "COT of NGN".$cot . " for transfer of NGN".$amount . " to ".$accountName.", ".$receiving_accountNo;
            
            $principalData = [
                "invoiceno" => $requestId,
                "systemid" => $systemId,
                "systemid2" => $systemId,
                "wemasystemid" => NULL,
                "clientname" => $clientname,
                "amount" => $amount,
                "paymentdatesql" => date("Y-m-d"),
                "withdrawaldate" => date("d/m/Y"),
                "withdrawaltime" => date("H:i:s a"),
                "author" => "OSB Money Transfer",
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
                "clientname" => $clientname,
                "amount" => $cot,
                "paymentdatesql" => date("Y-m-d"),
                "withdrawaldate" => date("d/m/Y"),
                "withdrawaltime" => date("H:i:s a"),
                "author" => "OSB COT Charges",
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
                "clientname" => "OSB Transaction COT Ledger",
                "paymentdate" => date("d/m/Y"),
                "paymenttime" => date("H:i:s a"),
                "paymentdatesql" => date("Y-m-d"),
                "amountpaid" => $cot,
                "goal" => "OSB Transaction COT Ledger",
                "definition" => $COTDebit_description,
                "earnings" => NULL,
                "earningspaid" => NULL,
                "author" => "OSB COT Charges",
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
                "client_name" => $clientname,
                "requestid" => $requestId,
                "bank_code" => $getBank["bank_code"],
                "bank_name" => $getBank["bank_name"],
                "account_name" => $accountName,
                "accountNo" => $receiving_accountNo,
                "amount" => $amount,
                "date_updated" => date("Y-m-d H:i:s"),
                "date_created" => date("Y-m-d H:i:s")
            ];
            
            $con->insertRecord("tbldaily_withdrawals", $principalData);
            $con->insertRecord("tbldaily_withdrawals", $cotData);
            
            $con->insertRecord("tbldaily_savings", $creditCotData);
            $con->insertRecord("tblwithdrawal_request", $pendingRequest);
            
            // Since we are done then let's delete the tmpbank_temp list for this session id...
            $con->deleteRecord("tbltemp_banklist", ["session_id" => $sessionId]);
            
            // Delete uncompleted transactions...
            $dateLike = date("Y-m-d");
            $con->runQuery("delete from tbltemp_banklist where date_created NOT LIKE '%$dateLike%");
            
            $respMsg = "Your request has been received. N".number_format($amount, 2)." will be deposited into ".$receiving_accountNo. " ".$getBank["bank_name"]. " \n Thank You";
        }
        $response .= "END ".$respMsg;
        
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "2" AND isset($explodeText['1']) AND isset($explodeText['2']) AND isset($explodeText['3']) AND $explodeText['3'] != "6") {
        $amount = $explodeText['1'];
        $receiving_accountNo = $explodeText['2'];
        $bank_input = $explodeText['3'];
        
        // WHich bank did user selected, do not forget that we used 'array_id' to hold user input...
        $getBank = $con->getSingleRecord("tbltemp_banklist", "*", " AND array_id ='$bank_input' AND action = 'populate' AND session_id = '$sessionId'", " ORDER BY id desc");
        $bankCode = $getBank['bank_code'];
        $bank_name = $getBank["bank_name"];
        
        //Let's verify the bank selected....
        $verifyAccount = $banks->verifyAccount($bankCode, $receiving_accountNo);
        $decode_response = json_decode($verifyAccount, true)['data'];
        
        if($decode_response['responseCode'] == 00) {
            $accountName = $decode_response['accountName'];   
            $response .= "CON Transfer of N".number_format($amount, 2). " to ". $accountName. " ".$receiving_accountNo . " at N".number_format($cot, 2)." charge \n Please enter your OSB PIN";
        } else {
            $response .= "END We could not verify the account number (".$receiving_accountNo.") belonging to ".$bank_name;
        }
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "2" AND isset($explodeText['1']) AND isset($explodeText['2'])) {
        $searchBanks = $con->getAllRecords("tblbanks_list", "*", " AND use_ussd = 'yes'", " order by bank_name ASC limit 5");
        $i = 1;
        $response .= "CON Select Recipient Bank \n";
        foreach($searchBanks as $searchBank) {
            $response .= $i." ".removeBracket($searchBank["bank_name"]) . "\n";
            
            //Since session & cookie didn't work then store it to DB
            $con->insertRecord("tbltemp_banklist", [
                "session_id" => $sessionId,
                "array_id" => $i,
                "bank_name" => $searchBank["bank_name"],
                "bank_code" => $searchBank["bank_code"],
                "action" => "populate"
            ]);
            
            $i++;
        }
        $response .= "6. Enter 3 letter of bank name \n";
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "2" AND isset($explodeText['1'])) {
        $response .= "CON Enter recipient Account Number";
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "2") {
        $response .= "CON Hi ".$clientname ."\n \n Enter amount you want to withdraw";
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "3" AND isset($explodeText['1'])) {
        $ussdPass = $explodeText['1'];
        if($ussdPass == "0000") {
            $respMsg = "Default USSD PIN (0000) cannot be used";
        }
        else if($user_ussd_id != $ussdPass) {
            $respMsg = "Password do not match";
        }
        else {
            $respMsg = "Hi ".$clientname.", \n Your current wallet balance is NGN".number_format($currBalance, 2)."\n Thank You";
        }
        $response .= "END ".$respMsg;
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "3") {
        $response .= "CON Hi ".$clientname ."\n \n Enter your OSB USSD Password";
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "4" AND isset($explodeText['1']) AND isset($explodeText['2']) AND isset($explodeText['3'])) {
        $currentUssd = $explodeText['1'];
        $newUssd = $explodeText['2'];
        $confNewUssd = $explodeText['3'];
        
        if($user_ussd_id != $currentUssd) {
            $respMsg = "Password do not match";
        }
        else if($newUssd != $confNewUssd) {
            $respMsg = "New and Confirm Password do not match";
        }
        else if($newUssd == '0000') {
            $respMsg = 'Default transaction pin (0000) can not be set as transaction pin';
        }
        else {
            $updateUssdPin = $con->updateTable('tblusers', 
                [
                    "ussd_id" => $newUssd
                ],
                [
                    "systemid" => $systemId
                ]
            );
            if($updateUssdPin) {
                $respMsg = "Hi ".$clientname.", \n Your USSD PIN has been reset successfully \n Thank You";
            }
            else {
                $respMsg = "Error processing your request";
            }
        }
        
        $response .= "END ". $respMsg;
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "4" AND isset($explodeText['1']) AND isset($explodeText['2'])) {
        $response .= "CON Confirm your New USSD PIN";
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "4" AND isset($explodeText['1'])) {
        $response .= "CON Enter your New USSD PIN";
    }
    else if(isset($explodeText['0']) AND $explodeText['0'] == "4") {
        $response .= "CON Enter your current USSD PIN";
    }
}

function getAllSettings() {
    global $con;
    
    $getSettings = $con->getAllRecords("tbl__fcmb_settings", "*", ""); 
    foreach($getSettings as $index => $record) {
        $feedback[$record['name']] = $record['value'];
    }
    return arrayToObject($feedback);
}

function removeBracket($data) {
    return trim(preg_replace('/\s*\([^)]*\)*/', '', $data));
}

function reformNo($phoneNo) {
    if(substr(str_replace("+", "" , $phoneNo), 0, 3) == '234') {
        $result = '0'.substr(str_replace("+", "" , $phoneNo), 3, 13);
    } else {
        $result = $phoneNo;
    }
    return $result;
}

function UserBalance($systemId) {
    global $con;
    $amountLeft = 0;
    
    // $totalSavings = "select SUM(amountpaid) from tbldaily_savings where systemid = '$systemId'";
    
    $getSavings = $con->getSingleRecord("tbldaily_savings", " COALESCE(sum(amountpaid), 0) as amount_deposited", " AND systemid = '$systemId'");
    
    $getWithdrawal = $con->getSingleRecord("tbldaily_withdrawals", " COALESCE(sum(amount), 0) as amount_withdrawn", " AND systemid = '$systemId'");
    
    $amountLeft = (float)($getSavings['amount_deposited'] - $getWithdrawal['amount_withdrawn']);
    
    return $amountLeft;
    
}

header("Content-type: text/plain");
echo $response;

// print_r($response);