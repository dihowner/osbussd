<?php

class Authenticate extends DB {
    protected $responseBody;
    public function __construct($con) {
        
        $this->con = $con;
        $this->table = "tbl__fcmb_credentials";
        
        $this->clientID = $this->getCredentials()["clientID"];
        $this->authPass = $this->getCredentials()["authPass"];
        $this->authIv = $this->getCredentials()["authIv"];
        $this->collectionAccnt = $this->getCredentials()["collectionAccnt"];
        $this->subscriptionKey = $this->getCredentials()["subscriptionKey"];
        $this->debitInfo = json_decode($this->getCredentials()["debitInfo"]);
        $this->debitAccountName = $this->debitInfo->account_name;
        $this->debitAccountNumber = $this->debitInfo->account_number;
        $this->debitBankVerificationNumber = $this->debitInfo->verification_number;
        
        ########### TEST CREDENTIAL ########### 
            // $this->clientID = "250";
            // $this->authPass = "Re0R%0Fd";
        ########### TEST CREDENTIAL ########### 
    }
    
    private function getCredentials() {
        $records = $this->con->getAllRecords($this->table, "*", "");
        foreach($records as $record => $key) {
            $response[$key['name']] = $key['value'];
        }
        
        $this->responseBody = $response;
        return $this->responseBody;
    }
    
    public function generateToken() {
        $utcdate = gmdate("Y-m-d\TH:i:s");
        $convertUTC = date("Y-m-dHis", strtotime($utcdate));
        $join_credential = $convertUTC.$this->clientID.$this->authPass;
        $xtoken = hash("sha512", $join_credential);
        return $xtoken;
    }
    
    public function utcTime() {
        $utcdate = gmdate("Y-m-d\TH:i:s");
        return $utcdate;
    }
    
    // SHowing on Navbars....
    public function navUtcTime() {
        $utcdate = gmdate("Y-m-d H:i:s");
        return $utcdate;
    }
    
}

?>