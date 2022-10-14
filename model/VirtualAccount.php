<?php
error_reporting(-1);
require_once "Transactions.php";

class VirtualAccount extends Authenticate {
    
    protected $responseBody;
    
    public function __construct($con) {
        $this->con = $con;
        $this->authenticate = new Authenticate($this->con);
        $this->transaction = new Transactions($this->con);
        
        $this->requestID = "OsB_RQID".$this->con->randID(7);
        $this->appKey = "OSB_APP".$this->con->randID(5);
        
        $this->clientReg_endpoint = "https://liveapi.fcmb.com/Virtualaccount-clone/VirtualAccounts/v1/clientRegistration";
        $this->updateclientReg_endpoint = "https://liveapi.fcmb.com/Virtualaccount-clone/VirtualAccounts/v1/clientRegistration";
        $this->single_endpoint = "https://liveapi.fcmb.com/Virtualaccount-clone/VirtualAccounts/v1/openVirtualAccount";
        $this->createBulk_endpoint = "https://liveapi.fcmb.com/Virtualaccount-clone/VirtualAccounts/v1/requestBulkVirtualAccount";
        $this->getBulk_endpoint = "https://liveapi.fcmb.com/Virtualaccount-clone/VirtualAccounts/v1/getBulkVirtualAccount?originalRequestID=";
        $this->updateVirtual_endpoint = "https://liveapi.fcmb.com/Virtualaccount-clone/VirtualAccounts/v1/updateVirtualAccountV2";
        
        //Fro Authentication file...
        $this->collectionAccnt = $this->authenticate->collectionAccnt;
        $this->clientID = $this->authenticate->clientID;
        $this->xToken = $this->authenticate->generateToken();
        $this->utcTime = $this->authenticate->utcTime();
        $this->subscriptionKey = $this->authenticate->subscriptionKey;
        $this->authIv = $this->authenticate->authIv;
        $this->authPass = $this->authenticate->authPass;
    }
    
    public function clientRegistration() {
        $body = [
            "requestId" => $this->requestID,
            "collection_Acct" => $this->collectionAccnt,
            "transaction_Notification_URL" => "https://www.osb.ng/secured/neo/notification/transaction.php",
            "name_inquiry_URL" => "https://www.osb.ng/secured/neo/notification/nameInquiry.php",
            "account_Creation_URL" => "https://www.osb.ng/secured/neo/notification/accountCreation.php",
            "productId" => 29,
            "appKey" => $this->appKey
        ];
        
        $body = json_encode($body);
        
        $result = $this->connector("post", $this->clientReg_endpoint, $body);
        return $result. " <br> ". $this->appKey . " <br> " . $this->requestID . " <br> " . $body;
    }
    
    public function updateClientRegistration() {
        $body = [
            "requestId" => $this->requestID,
            "collection_Acct" => $this->collectionAccnt,
            "transaction_Notification_URL" => "https://www.osb.ng/secured/neo/notification/transaction.php",
            "name_inquiry_URL" => "https://www.osb.ng/secured/neo/notification/nameInquiry.php",
            "account_Creation_URL" => "https://www.osb.ng/secured/neo/notification/accountCreation.php",
            "productId" => 29
        ];
        
        $body = json_encode($body);
        
        $result = $this->connector("post", $this->updateclientReg_endpoint, $body);
        return $result . " <br> " . $this->requestID . " <br> " . $body;
    }
    
    public function generatesingle_virtual($client_name) {
        
        $body = [
            "requestId" => $this->requestID,
            "collectionAccount" => $this->collectionAccnt,
            "preferredName" => $client_name,
            "clientId" => $this->clientID,
            "external_Name_Validation_Required" => false,
            "productId" => 29
        ];
        $requestBody = json_encode($body);
        
        $response = $this->connector("post", $this->single_endpoint, $requestBody);
        
        $decode_response = json_decode($response);
        
        $request_id = $this->requestID;
        $collectionAccnt = $this->collectionAccnt;
        $accountNo = $decode_response->data;
        
    
        if($decode_response->code == 00 AND $decode_response->description == "success") {
            
            // Save the virtual account request...
            $this->transaction->createVirtual($request_id, "", 0, $collectionAccnt, $accountNo, $requestBody, $response);
            
            $this->responseBody = [
                "message" => "Account Number is ".$accountNo.". Request ID is ".$request_id,
                "status" => "success",
                "account_number" => $accountNo,
                "request_id" => $request_id
            ];
        }
        else {
            
            // Save the failed virtual account request...
            $this->transaction->createFailedVirtual($request_id, "", 0, $collectionAccnt, $accountNo, $requestBody, $response);
            
            $this->responseBody = [
                "message" => "Error generating virtual account number",
                "status" => "failed",
                "request_id" => $request_id
            ];
        }
        return json_encode($this->responseBody);
        // return $response . " ". $requestBody . " ".$this->utcTime . " ".$this->xToken;
    }
    
    public function getSingleVirtual($request_id, $virtualAccountNo) {
        
        $endpoint = "https://liveapi.fcmb.com/Virtualaccount-clone/VirtualAccounts/v1/GetVirtualAccountNew?virtualAccount=".$virtualAccountNo."&requestId=".$request_id;
        $getSingleVA = $this->connector("get", $endpoint);
        return $getSingleVA;
    }
    
    public function generatebulk_virtual($totalNo) {
        $body = [
            "requestId" => $this->requestID,
            "collectionAccount" => $this->collectionAccnt,
            "no_of_Accounts" => $totalNo,
            "prefered_Name" => "OsB Bulk",
            "external_Name_Validation_Required" => false,
            "productId" => 29
        ];
        
        $requestBody = json_encode($body);
        $collectionAccnt = $this->collectionAccnt;
        $request_id = $this->requestID;
        
        $response = $this->connector("post", $this->createBulk_endpoint, $requestBody);
        $decode_generate = json_decode($response);
        
        if($decode_generate->code == 00) {
            
            $this->transaction->createVirtual($request_id, "bulk", $totalNo, $collectionAccnt, "", $requestBody, $response);
            
            return json_encode([
                "status" => 200,
                "requestID" => $this->requestID,
                "no_of_Accounts" => $totalNo
            ]);
        }
        else {
            return json_encode([
                "status" => false,
                "message" => "Request failed or Reference already exists"
            ]);
        }
        
    }
    
    public function getbulkvirtual_accountno($request_id) {
        $endpoint = $this->getBulk_endpoint.$request_id;
        $getBulk = $this->connector("get", $endpoint);
        return $getBulk;
    }
    
    public function updateVirtualAccount(String $name, $accountNo) {
        $body = [
            "requestId" => $this->requestID,
            "virtualAccount" => $accountNo,
            "clientID" => $this->clientID,
            "preferredname" => $name,
            "external_Name_Validation_Required" => false,
            "deactivateAccount" => false
        ];
        $body = json_encode($body);
        $updateVirtual = $this->connector("post", $this->updateVirtual_endpoint, $body);
        return $updateVirtual;
    }
    
    private function connector($method, $url, $body = "") {
        
        $headers = array (
            'client_id: '.$this->clientID,
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
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        
    	$response = curl_exec($curl);
    	curl_close($curl);
        return $response;
    }
    
    public function creditMember($encryptedData) {
        $decryptData = $this->decryptData($encryptedData);
        if($decryptData !== false) {
            $decode_data = json_decode($decryptData);
            
            if($decode_data->ClientID == $this->clientID AND $decode_data->Response == "00") {
                $users = new Users($this->con);
                
                $accountPaidTo = $decode_data->Virtual_Account;
                
                $getUser = $users->getUser($accountPaidTo);
                
                if($getUser === false) {
                    $this->responseBody = "Virtual Account ($accountPaidTo) does not exists";
                } else {
                    
                    $clientname = $getUser["fname"] . " ".$getUser["lname"];
                    $amountPaid = $decode_data->Tran_Amount;
                    $systemid = $accountPaidTo;
                    $transactId = $decode_data->Tran_ID;
                    
                    $checkTrans = $this->con->getSingleRecord($this->transaction->savingsTbl, "*", " AND invoiceno = '$transactId'");
                    
                    if($checkTrans == NULL) {
                        $createSaving = $this->transaction->createSavings($systemid, $clientname, $amountPaid, $transactId);
                        
                        if($createSaving) {
                            $this->responseBody = "Account (".$systemid.") credited successfully with NGN".$amountPaid;
                        }
                        else {
                            $this->responseBody = "Error crediting user account (".$systemid.")";
                        }
                    }
                    else {
                        $this->responseBody = "Payment already exists";
                    }
                }
            }
            else {
                $this->responseBody = "Unknown client ID";
            }
        }
        else {
            $this->responseBody = "Unable to decrypt data";
        }
        file_put_contents("../notification/creditlog.txt", $this->responseBody. "\n", FILE_APPEND);
        return $this->responseBody;
    }
    
    private function decryptData($encryptedData) {
        $cipher = 'aes-192-cbc';
        $decrypt = openssl_decrypt($encryptedData, $cipher, $this->authPass, 0, $this->authIv);
        $decryptResult = ($decrypt == "" ? false:$decrypt); 
        return $decryptResult;
    }
    
}
?>