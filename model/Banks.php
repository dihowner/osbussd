<?php

require_once "Transactions.php";

class Banks extends Authenticate {
    public function __construct($con) {
        
        $this->con = $con;
        $this->authenticate = new Authenticate($this->con);
        $this->transaction = new Transactions($this->con);
        
        // $this->getAllBankEndpoint = "https://devapi.fcmb.com/interbnktransfer/api/ActiveBanks/GetAllActiveBanks"; => Not used
        $this->getActiveBankEndpoint = "https://liveapi.fcmb.com/OtherBankTransfer/api/ActiveBanks/GetAllActiveBanks";
        $this->verifyAccountNoEndpoint = "https://liveapi.fcmb.com/OtherBankTransfer/api/Transfer/nip-nameEnquiry"; 
        $this->interBankTFEndpoint = "https://liveapi.fcmb.com/OtherBankTransfer/api/Transfer/do-nip-transfer-b2c-v2";
        $this->fcmbfcmbTFEndpoint = "https://liveapi.fcmb.com/intra-bankTrf/api/IntrabankTransfer/b2cTransfer";
        $this->requestID = "OsB-RQID".$this->con->randID(7);
        
        // From Authentication file...
        $this->collectionAccnt = $this->authenticate->collectionAccnt;
        $this->clientID = $this->authenticate->clientID;
        $this->xToken = $this->authenticate->generateToken();
        $this->utcTime = $this->authenticate->utcTime();
        $this->debitAccountName = $this->authenticate->debitAccountName;
        $this->debitAccountNumber = $this->authenticate->debitAccountNumber;
        $this->debitBankVerificationNumber = $this->authenticate->debitBankVerificationNumber;
        $this->subscriptionKey = $this->authenticate->subscriptionKey;
     
        $this->table = 'tblbanks_list';
    }
    
    // Via API
    public function bankLists() {
        $result = $this->connector("GET", $this->getActiveBankEndpoint);
        $decode_bank = json_decode($result, true)["data"];
        
        $recompute = [];
        
        for($i = 0; $i < count($decode_bank); $i++) {
            $recompute[$i]["code"] = $decode_bank[$i]["bankCode"];
            $recompute[$i]["bankName"] = $decode_bank[$i]["bankName"];
            $recompute[$i]["bankInitial"] = $decode_bank[$i]["bankShortName"];
        }
        return json_encode($recompute);
    }
    
    // Via DB
    public function getAllBanks() {
        $result = $this->con->getAllRecords($this->table, "*" , " ORDER BY bank_name asc");
        
        if($result == NULL) {
            return false;
        }
        else {
            return $result;
        }
    }
    
    public function getBankByBankCode($bank_code) {
        $result = $this->con->getSingleRecord($this->table, "*" , " AND bank_code = '$bank_code'", " ORDER BY bank_name asc");
        if($result == NULL) {
            return false;
        }
        else {
            return $result;
        }
    }
    
    public function InterBankTransfer($requestId = "", $account_number, $bankCode, $bankVerificationNumber, $sessionID, $channelCode, $accountName, $amount, $narration) {
        $requestID = ($requestId == "" ? $this->requestID : $requestId);
        $body = [
            "requestId" => $requestID,
            "clientId" => $this->clientID,
            "productId" => 3,
            "nameEnquiryRef" => $sessionID,
            "destinationInstitutionCode" => $bankCode,
            "channelCode" => $channelCode,
            "debitAccountName" => $this->debitAccountName,
            "debitAccountNumber" => $this->debitAccountNumber,
            "debitBankVerificationNumber" => $this->debitBankVerificationNumber,
            "debitKYCLevel" => "1",
            "beneficiaryAccountName" => $accountName,
            "beneficiaryAccountNumber" => $account_number,
            "beneficiaryKYCLevel" => "1",
            "beneficiaryBankVerificationNumber" => $bankVerificationNumber,
            "transactionLocation" => "Ikorodu",
            "narration" => $narration,
            "amount" => $amount
        ];
        $requestBody = json_encode($body);
        
        $transferResponse = $this->connector("POST", $this->interBankTFEndpoint, $requestBody);
        $_SESSION['response'] = $transferResponse;
        
        $decode_response = json_decode($transferResponse, true);
        $transRef = $decode_response["data"]["paymentReference"];
        $request_id = $requestID;
        $debitAccount = $this->debitAccountNumber;
        $creditAccount = $account_number;
        $status = $decode_response["code"];
        
        $this->transaction->createFCMBToFCMBTransfer($debitAccount, $creditAccount, $requestBody, $request_id, $amount, $transRef, $transferResponse, $status);
        
        if($status == '00') {
            $response_body = [
                "status" => "Approved or Completed Successfully",
                "request_id" => $requestID,
                "message" => "Transfer of N".$amount." has been made to ". $account_number
            ];
        }
        else if($status == '01') {
            $response_body = [
                "status" => "Possible Duplicate record",
                "message" => "Your transaction may have been processed already. Please check your notifications or try again later. Reqest ID : ".$this->requestID
            ];
        }
        else if($status == '26') {
            $response_body = [
                "status" => "Duplicate record",
                "message" => "Transaction reference ". $this->requestID. " already exists"
            ];
        }
        else if($status == '51') {
            $response_body = [
                "status" => "Insufficient funds",
                "message" => "No sufficient fund (N".$amount.") to process request. Reqest ID : ".$this->requestID
            ];
        }
        else if($status == '99') {
            $response_body = [
                "status" => "Invalid Request",
                "message" => "Debit account number does not match Reqest ID : ".$this->requestID
            ];
        }
        else {
            $response_body = [
                "status" => "Invalid Request",
                "message" => $decode_response["description"]. ". Reqest ID : ".$this->requestID
            ];
        }
        return json_encode($response_body);
    }
    
    public function verifyAccount(string $code, string $accNo) {
        
        $headers = array (
            'client_id: '.$this->clientID,
            'Content-Type: application/json',
            'client_key: APIM_client',
            'Cache-Control: no-cache',
            'Ocp-Apim-Subscription-Key: '.$this->subscriptionKey,
            'x-token: '. $this->xToken,
            'UTCTimestamp: '. $this->utcTime
        );
        
        $request_body = '{
            "destinationInstitutionCode": "'.$code.'",
            "accountNumber": "'.$accNo.'"
        }';
        
        $curl = curl_init($this->verifyAccountNoEndpoint);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_URL, $this->verifyAccountNoEndpoint);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request_body);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
        
    }
    
    public function verifyFCMBAccount($accountNo) {
        $verifyEndpoint = "https://liveapi.fcmb.com/intra-bankTrf/api/AccountInquiry/nameEnquiry?accountNumber=".$accountNo;
        return $this->connector("GET", $verifyEndpoint);
    }
    
    public function FcmbToFcmbTransfer($account_number, $amount) {
        $body = [
            "requestId" => $this->requestID,
            "productId" => 11,
            "debitAccountNo" => $this->debitAccountNumber,
            "creditAccountNo" => $account_number,
            "debitMerchantForCharge" => true,
            "narration" => "Interbank test by Quorium Solutions",
            "amount" => $amount
        ];
        $requestBody = json_encode($body);
        
        // Make the transfer...
        $transferResponse = $this->connector("POST", $this->fcmbfcmbTFEndpoint, $requestBody);
        $_SESSION['initiate_tf_bank'] = $transferResponse;
        $decode_response = json_decode($transferResponse, true);
        
        $transaction_reference = isset($decode_response['data']) ? $decode_response['data']['tran_id'] : NULL;
        $status = $decode_response['code'];
        
        $this->transaction->createFCMBToFCMBTransfer($this->debitAccountNumber, $account_number, $requestBody, $this->requestID, $amount, $transaction_reference, $transferResponse, 'pending');

        if($status == '00') {
            $this->transaction->updateTransaction('fcmbfcmb', ["status_response" => "success"], $this->requestID);
            $response_body = [
                "status" => 200,
                "status_text" => "Approved or Completed Successfully",
                "request_id" => $this->requestID,
                "message" => "Transfer of N".$amount." has been made to ". $account_number.". Reference ". $this->requestID
            ];
        }
        else if($status == '01') {
            $this->transaction->updateTransaction('fcmbfcmb', ["status_response" => "failed"], $this->requestID);
            $response_body = [
                "status" => 400,
                "status_text" => "Possible Duplicate record",
                "request_id" => $this->requestID,
                "message" => "Your transaction may have been processed already. Please check your notifications or try again later"
            ];
        }
        else if($status == '26') {
            $response_body = [
                "status" => 400,
                "status_text" => "Duplicate record",
                "request_id" => $this->requestID,
                "message" => "Transaction reference ". $this->requestID. " already exists"
            ];
        }
        else {
            $response_body = [
                "status" => 401,
                "status_text" => "System Error",
                "request_id" => $this->requestID,
                "message" => "Unknown server response. Reference ". $this->requestID
            ];
        }
        return json_encode($response_body);
    }
    
    private function connector($method, $url, $body = "") {
        
        $headers = array (
            'client_id: '.$this->clientID,
            'client_key: APIM_client',
            'Content-Type: application/json',
            'Cache-Control: no-cache',
            'Ocp-Apim-Subscription-Key: '.$this->subscriptionKey,
            'x-token: '. $this->xToken,
            'UTCTimestamp: '. $this->utcTime
        );
        
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        if($body != "") {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        
    	$response = curl_exec($curl);
    	curl_close($curl);
        return $response;
    }
    
}