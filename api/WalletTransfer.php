<?php
require "../connect.php";

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if($_SERVER['REQUEST_METHOD'] == "POST") {
    
    $getContent = !(empty(stream_get_contents(fopen('php://input', 'r')))) ? stream_get_contents(fopen('php://input', 'r')) : $_REQUEST; //Open and get the content, the content is in json, then decode
    
    if(!is_array($getContent)) {
        $contentDetail = json_decode($getContent, true);
    }
    else {
        $contentDetail = $getContent;
    }
    
    $senderId = $contentDetail["senderId"];
    $receiverId = $contentDetail["receiverId"];
    $amount = $contentDetail["amount"];
    $transaction_pin = $contentDetail["ussd_pin"];
    
    if($senderId == "" OR $receiverId == "" OR $amount == "" OR $transaction_pin == "") {
        $response = ["status"=> false, "message" => "Incomplete or No data value was sent" ];
    } 
    else if(strpos($amount, ".") !== false) {
        $response = ["status"=> false, "message" => "Invalid amount passed. Amount must not have a decimal value" ];
    }
    else {
        
        // Verify Sender through the API we have already....
        $verifyOSBSender = sendRequest(BASE_URL."api/verifyOSBID.php", ["userId" => $senderId]);
        $decode_verify_sender = json_decode($verifyOSBSender);
                
        // Verify Receiver
        $verifyOSBReceiver = sendRequest(BASE_URL."api/verifyOSBID.php", ["userId" => $receiverId]);
        $decode_verify_receiver = json_decode($verifyOSBReceiver);
        
        if(isset($decode_verify_sender->status)) {
            $response = ["status"=> false, "message" => "Sender: " . $decode_verify_sender->message];
        } 
        else if(isset($decode_verify_receiver->status)) {
            $response = ["status"=> false, "message" => "Receiver: " . $decode_verify_receiver->message];
        }
        else if($transaction_pin == "0000") {
            $response = ["status"=> false, "message" => "You cannot use default transaction pin to perform a transaction"];
        }
        else if($decode_verify_sender->ussd_id != $transaction_pin) {
            $response = ["status"=> false, "message" => "Incorrect transaction pin supplied"];
        }
        else {
            $currentBalance = $decode_verify_sender->wallet_balance;
            
            if($currentBalance < $amount) {
                $response = ["status"=> false, "message" => "Insufficient wallet balance"];
            }
            else {
                $client_name = $decode_verify_sender->fname . " ". $decode_verify_sender->lname; 
                $debitNarration = "Transfer of NGN".$amount." to ".$receiverId;
                $requestId = "OsB_RQID".$con->randID(7);
                
                $debitData = [
                    "invoiceno" => $requestId,
                    "systemid" => $senderId,
                    "systemid2" => $senderId,
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
                    "done" => "Yes",
                    "request" => NULL,
                    "companyname" => "Osemo Borealis Ltd.",
                    "branchid" => "000001",
                    "taken" => "Yes",
                    "corrected" => "Yes",
                    "definition" => $debitNarration,
                    "transaction_type" => "ussd"
                ];
                
                $receiver_name = $decode_verify_receiver->fname . " ". $decode_verify_receiver->lname; 
                $creditNarration = "Received transfer fund of NGN".$amount." from ".$senderId;
                
                $creditData = [
                    "invoiceno" => $requestId,
                    "systemid" => $receiverId,
                    "systemid2" => $receiverId,
                    "wemasystemid" => NULL,
                    "clientname" => $receiver_name,
                    "paymentdate" => date("d/m/Y"),
                    "paymenttime" => date("H:i:s a"),
                    "paymentdatesql" => date("Y-m-d"),
                    "amountpaid" => $amount,
                    "goal" => "OsB Money Transfer",
                    "definition" => $creditNarration,
                    "earnings" => NULL,
                    "earningspaid" => NULL,
                    "author" => "OsB Money Transfer",
                    "locationcode" => "000",
                    "ddate" => date("d/m/Y"),
                    "ddates" => date("m/d/Y"),
                    "dweek" => NULL,
                    "done" => $receiverId,
                    "requests" => NULL,
                    "agent" => NULL,
                    "savings" => NULL,
                    "accounts" => NULL,
                    "md" => NULL,
                    "updateamt" => NULL,
                    "companyname" => "Osemo Borealis Ltd.",
                    "branchid" => "000001",
                    "corrected" => "Yes",
                    "duplicate_entries" => NULL,
                    "transaction_type" => "ussd"
                ];
                
                $con->beginTransaction();
                try {
                    if($con->insertRecord("tbldaily_withdrawals", $debitData)) {
                        if($con->insertRecord("tbldaily_savings", $creditData)) {
                            $con->commit();
                            $response = ["status"=> true, "message" => "Transaction was successful. Beneficiary ($receiverId) has been credited with NGN".number_format($amount, 2)." \n Thank You"];
                        }
                        else {
                            $con->rollBack();
                            $response = ["status"=> false, "message" => "Unexpected error occurred. We could not credit beneficiary account." ];
                        }
                    }
                    else {
                        $con->rollBack();
                        $response = ["status"=> false, "message" => "Unexpected error occurred. We could not debit your account" ];
                    }
                }
                catch(Throwable $e) {
                    $con->rollBack();
                    echo $e->getMessage();
                }
            }
        }
    }
    
}
else {
    $response = ["status"=> false, "message" => "Only POST method allowed" ];
}
echo json_encode($response, JSON_PRETTY_PRINT);

?>